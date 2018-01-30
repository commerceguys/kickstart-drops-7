<?php

/**
 * @file
 * Commerce Payment method info callbacks.
 */

/**
 * Payment method callback: submit form.
 */
function commerce_amazon_login_and_pay_submit_form($payment_method, $pane_values, $checkout_pane, $order) {
  $form['info'] = array(
    '#markup' => t('Complete the payment using Amazon Pay with the address and wallet selected in previous steps.'),
  );

  $wrapper = entity_metadata_wrapper('commerce_order', $order);
  $form['amazon_order_reference_id'] = array(
    '#type' => 'hidden',
    '#default_value' => $wrapper->{AmazonLPA::REFERENCE_ID_FIELD}->value(),
  );
  $form['wallet_widget'] = array(
    '#theme' => 'commerce_amazon_wallet_widget',
    '#display_mode' => path_is_admin(current_path()) ? 'read' : '',
    '#order_reference_id' => $wrapper->{AmazonLPA::REFERENCE_ID_FIELD}->value(),
  );
  return $form;
}

/**
 * Payment method callback: submit form validation.
 *
 * @see commerce_checkout_by_amazon_submit_form_validate()
 */
function commerce_amazon_login_and_pay_submit_form_validate($payment_method, $pane_form, $pane_values, $order, $form_parents = array()) {
  if (!isset($order->data['commerce_amazon_lpa'])) {
    form_set_error('commerce_payment', t('Please select a payment method to continue.'));
    drupal_set_message(t('Some information for proceeding with the payment might be missing, please review previous steps in order to complete at least address and wallet information.'), 'error');
    return FALSE;
  }

  // There should be no more validation error before purchasing with Amazon.
  if (form_get_errors()) {
    return FALSE;
  }
}

/**
 * Payment method callback: submit form submission.
 *
 * If an order is digital, the order reference will have been generated in the
 * submit form, we save it here.
 */
function commerce_amazon_login_and_pay_submit_form_submit($payment_method, $pane_form, $pane_values, $order, $charge) {
  /** @var EntityDrupalWrapper $order_wrapper */
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);

  if (!commerce_amazon_lpa_order_is_shippable($order)) {
    try {
      $order_wrapper->{AmazonLPA::REFERENCE_ID_FIELD} = $pane_values['amazon_order_reference_id'];
      $order->data['commerce_amazon_lpa']['order_reference'] = AmazonLPA::instance()->getOrderRef($order_wrapper);
      $order_wrapper->save();
    }
    catch (Exception $e) {
      drupal_set_message(t('There was an error setting the Amazon order reference'), 'error');
      return FALSE;
    }
  }
}

/**
 * Payment method callback; generation callback for the payment redirect form.
 *
 * This actually processes the order and transaction with Amazon Payments and
 * then moves forward with the checkout.
 */
function commerce_amazon_login_and_pay_redirect_form($form, &$form_state, $order, $payment_method) {
  // We're in ERP mode and aren't doing any processing.
  if (AmazonLPA::is_erp_mode()) {
    commerce_payment_redirect_pane_next_page($order, t('Amazon Login and Pay operating in ERP mode, skipped payment.'));
    drupal_goto(commerce_checkout_order_uri($order));
  }
  $api = AmazonLPA::instance();
  /** @var EntityDrupalWrapper $order_wrapper */
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
  $order_reference = $api->getOrderRef($order_wrapper);

  // On error we will redirect user's back to the checkout page with the
  // commerce_payment pane. We load it here to.
  $checkout_panes = commerce_checkout_panes();
  $commerce_payment_pane = $checkout_panes['commerce_payment'];

  // Process the transaction.
  try {
    $amazon_order_state = $order_reference['OrderReferenceStatus']['State'];

    if ($amazon_order_state == 'Draft') {
      $response = $api->setOrderRef($order_wrapper);
      if (!empty($response['Constraints'])) {
        $constraint = $response['Constraints']['Constraint'];
        throw new AmazonApiException(
          'ConstraintsExist',
          $response,
          t('Unable to set the order reference for @order_id: @reason', array(
            '@order_id' => $order_wrapper->getIdentifier(),
            '@reason' => t('@code - @message', array(
              '@code' => $constraint['ConstraintID'],
              '@message' => $constraint['Description'],
            )),
          )));
      }
    }

    $api->confirmOrderRef($order_wrapper);

    // Create a transaction.
    $transaction = commerce_payment_transaction_new($payment_method['method_id'], $order->order_id);
    $transaction->instance_id = $payment_method['instance_id'];

    // If the authorization mode is manual, create a placeholder transaction.
    if (AmazonLPA::get_authorization_mode() == AmazonLPA::AUTH_MANUAL) {
      $transaction->status = COMMERCE_PAYMENT_STATUS_PENDING;
      $transaction->data['commerce_amazon_lpa']['environment'] = variable_get('commerce_amazon_lpa_environment', AmazonLPA::ENV_SANDBOX);
      $transaction->data['commerce_amazon_lpa']['transaction_type'] = 'authorization';
      $transaction->amount = $order_wrapper->commerce_order_total->amount->value();
      $transaction->currency_code = $order_wrapper->commerce_order_total->currency_code->value();
      commerce_payment_transaction_save($transaction);
    }
    // Otherwise run an authorization to be done async or sync.
    else {
      // Authorize so we can manually capture or automatically when shipped.
      // If set to CAPTURE_AUTH_CAPTURE, then do capture with the authorization.
      $data = $api->authorize($order_wrapper, AmazonLPA::get_capture_mode() == AmazonLPA::CAPTURE_AUTH_CAPTURE ? TRUE : FALSE);
      $api->processAuthorizeTransaction($transaction, $data);

      // Mark the payment as a failure.
      if ($transaction->status == COMMERCE_PAYMENT_STATUS_FAILURE) {
        throw new AmazonApiException('AuthorizationStatus', $data, 'Payment failed');
      }
    }
  }
  catch (AmazonApiException $e) {
    $order_reference = $api->getOrderRef($order_wrapper);
    $order->data['commerce_amazon_lpa']['order_reference'] = $order_reference;

    $error_message = $e->getMessage();
    $order_next_status = 'checkout_' . $commerce_payment_pane['page'];
    if ($e->getErrorCode() == 'ConstraintsExist') {
      foreach ($order_reference['Constraints'] as $constraint) {
        switch ($constraint['ConstraintID']) {
          case 'PaymentMethodNotAllowed':
            $error_message = t('The selected payment method is not available for this transaction. Please select another one or add a new payment method to the wallet widget.');
            break;

          case 'PaymentPlanNotSet':
            $error_message = t('No payment instrument has been selected for this order, please try to refresh the page or add a new payment instrument in the wallet widget.');
            break;
        }
      }
    }
    elseif ($e->getErrorCode() == 'AuthorizationStatus') {
      $data = $e->getResponse();
      switch ($data['AuthorizationStatus']['ReasonCode']) {
        case 'AmazonRejected':
          // Amazon already closed the transaction.
          $error_message = t('Your payment could not be processed. Please try to place the order again using another payment method.');
          $order_next_status = 'cart';
          break;

        case 'TransactionTimedOut':
          $api->cancel($order_wrapper);
          $error_message = t('Your payment could not be processed. Please try to place the order again using another payment method.');
          $order_next_status = 'cart';
          break;

        case 'ProcessingFailure':
          $api->cancel($order_wrapper);
          $error_message = t('Your order could not be processed due to a system error. Please try to place the order again.');
          $order_next_status = 'cart';
          break;

        case 'InvalidPaymentMethod':
        default:
          $error_message = t('Your payment could not be processed, please follow the instructions in the payment method box.');
      }
    }

    if ($order_next_status == 'cart') {
      commerce_amazon_lpa_user_logout($order_wrapper->owner->value());
      $destination = 'cart';
    }
    else {
      $destination = commerce_checkout_order_uri($order);
    }

    drupal_set_message($error_message, 'error');
    commerce_order_status_update($order, $order_next_status, FALSE, TRUE, $e->getMessage());
    drupal_goto($destination);
  }

  commerce_payment_redirect_pane_next_page($order, t('Order paid with Amazon Payments'));
  drupal_goto(commerce_checkout_order_uri($order));
}
