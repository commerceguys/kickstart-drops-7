<?php

/**
 * @file
 * Various page callbacks.
 */

/**
 * Pay with Amazon page callback.
 *
 * This is the page callback used by the Pay With Amazon link. This sets a
 * flag on the order to alert the rest of the module this will use Amazon as its
 * checkout method.
 *
 * @return int|array
 *   MENU_* Value that the page manager will use to set the status.
 */
function commerce_amazon_lpa_checkout() {
  if (variable_get('commerce_amazon_lpa_popup', 'popup') != 'popup' && !isset($_COOKIE['amazon_Login_accessToken'])) {
    return array(
      '#attached' => array(
        'library' => array(array('commerce_amazon_lpa', 'amazon_widgets')),
        'js' => array(
          drupal_get_path('module', 'commerce_amazon_lpa') . '/js/commerce-amazon-lpa-redirect.js',
        ),
      ),
    );
  }

  $api = AmazonLPA::instance();
  $order = commerce_cart_order_load($GLOBALS['user']->uid);
  // If the user does not have access to checkout the order, return a 404. We
  // could return a 403, but then the user would know they've identified a
  // potentially valid checkout URL.
  $user_info = $api->getUserInfo();
  if (empty($order) || !commerce_checkout_access($order) || empty($user_info)) {
    drupal_goto('cart');
  }

  // Ensure user is logged in. If not, do so on their behalf.
  if (AmazonLPA::get_operation_mode() == AmazonLPA::OPERATION_MODE_LOGIN_AND_PAY && !user_is_logged_in()) {
    commerce_amazon_lpa_external_login();
  }

  // If an order is being saved without a contract ID, assume it has just left
  // the cart and beginning life in checkout.
  // Set the account info for the order.
  $order->data['commerce_amazon_lpa']['user_info'] = $user_info;
  if (empty($order->mail)) {
    $order->mail = $user_info['email'];
  }

  // Check if the order is re-entering Amazon checkout, and that is has
  // a valid state.
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
  if ($api->getOrderReferenceId($order_wrapper)) {
    $order_reference = $api->getOrderRef($order_wrapper);
    $amazon_order_state = $order_reference['OrderReferenceStatus']['State'];
    if ($amazon_order_state != 'Draft') {
      $order_wrapper->{AmazonLPA::REFERENCE_ID_FIELD} = '';
    }
  }

  if (commerce_order_save($order)) {
    drupal_goto('checkout');
  }
  else {
    drupal_set_message(t('There was an error initiating the Amazon checkout, please try again'), 'error');
    drupal_goto('cart');
  }
}

/**
 * Page callback for handling IPN notifications.
 *
 * @throws \Exception
 *   Amazon Exception.
 */
function commerce_amazon_lpa_process_ipn() {
  $api = AmazonLPA::instance();

  $headers = array();

  foreach ($_SERVER as $name => $value) {
    if (substr($name, 0, 5) == 'HTTP_') {
      $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
    }
  }

  try {
    $ipn_handler = new AmazonPayIpnHandler($headers, file_get_contents('php://input'));
  }
  catch (\Exception $e) {
    return MENU_ACCESS_DENIED;
  }
  $ipn_message = $ipn_handler->toArray();

  commerce_amazon_lpa_add_debug_log(t('Amazon IPN debug: !debug', array('!debug' => '<pre>' . print_r($ipn_message, TRUE) . '</pre>')));

  // Decide what to do based on the notification type. Amazon defines several
  // types of notifications. See link below. However, the notification types
  // they define don't appear in the actual responses. e.g.
  // AuthorizationNotification is defined in the docs, but PaymentAuthorize is
  // what actually gets sent with the IPN notification.
  // @see https://payments.amazon.com/documentation/apireference/201757720
  switch ($ipn_message['NotificationType']) {
    case 'OrderReferenceNotification':
      $data = $ipn_message['OrderReference'];
      $order = commerce_amazon_lpa_order_from_amazon_reference_id($data['AmazonOrderReferenceId']);

      if (empty($order)) {
        watchdog('commerce_amazon_lpa', 'Unable to find matching order for reference @id.', array('@id' => $data['AmazonOrderReferenceId']), WATCHDOG_ERROR);
        break;
      }

      $order->data['commerce_amazon_lpa']['order_reference'] = $data;

      switch ($data['OrderReferenceStatus']['State']) {
        case 'Open':
          if (AmazonLPA::AUTH_SYNC != AmazonLPA::get_authorization_mode()) {
            $balance = commerce_payment_order_balance($order);

            if ($balance['amount'] <= 0) {
              break;
            }

            /** @var EntityDrupalWrapper $order_wrapper */
            $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
            $data = $api->authorize($order_wrapper, AmazonLPA::get_capture_mode() == AmazonLPA::CAPTURE_AUTH_CAPTURE ? TRUE : FALSE, array(
              'amount' => $balance['amount'],
              'currency_code' => $balance['currency_code'],
            ));
            commerce_amazon_lpa_add_debug_log(t('IPN authorize: !debug', array('!debug' => '<pre>' . print_r($data, TRUE) . '</pre>')));

            $transaction = commerce_payment_transaction_new('commerce_amazon_login_and_pay', $order->order_id);
            $transaction->amount = $balance['amount'];
            $transaction->currency_code = $balance['currency_code'];
            $api->processAuthorizeTransaction($transaction, $data);

            $authorization_details = $api->getAuthorizationDetails($data['AmazonAuthorizationId']);
            if (isset($authorization_details['AuthorizationBillingAddress'])) {
              $billing_address = $authorization_details['AuthorizationBillingAddress'];
              try {
                commerce_amazon_lpa_amazon_address_to_customer_profile($order, 'billing', $billing_address);
                commerce_order_save($order);
              }
              catch (Exception $e) {
                watchdog('commerce_amazon_lpa', 'Error processing order billing information for Amazon: !error', array('!error' => '<pre>' . print_r($data, TRUE) . '</pre>'), WATCHDOG_ERROR);
              }
            }
          }
          break;

        case 'Canceled':
          if ('canceled' != $order->status) {
            commerce_order_status_update($order, 'cart', TRUE, TRUE, t('Order reference canceled by Amazon. Reason code: @code', array(
              '@code' => $data['OrderReferenceStatus']['ReasonCode'],
            )));
          }
          break;

        case 'Suspended':
          commerce_order_status_update($order, $order->status, TRUE, TRUE, t('Suspended by Amazon. Reason code: @code', array(
            '@code' => $data['OrderReferenceStatus']['ReasonCode'],
          )));
          break;

        case 'Closed':
          if (AmazonLPA::AUTH_SYNC != AmazonLPA::get_authorization_mode()) {
            $reason_code = $data['OrderReferenceStatus']['ReasonCode'];
            if ($reason_code == 'Expired' || $reason_code == 'AmazonClosed') {
              $order_status = 'cart';
            }
            else {
              $order_status = 'completed';
            }

            if ($order_status != $order->status) {
              commerce_order_status_update($order, $order_status, TRUE, TRUE, t('Closed notification by Amazon. Reason code: @code', array(
                '@code' => $data['OrderReferenceStatus']['ReasonCode'],
              )));
            }
          }
          break;
      }

      commerce_order_save($order);
      break;

    case 'PaymentAuthorize':
    case 'AuthorizationNotification':
      $data = $ipn_message['AuthorizationDetails'];

      $transaction = commerce_amazon_lpa_remote_payment_transaction_load($data['AmazonAuthorizationId']);
      // If we have no transaction, we must look up the matching authorization
      // remote ID. This lets us know the authorization has been closed because
      // it has met its maximum amount of captures.
      if (!$transaction && !empty($data['IdList']['Id'])) {
        $capture_id = $data['IdList']['Id'];
        $capture_transaction = commerce_amazon_lpa_remote_payment_transaction_load($capture_id);
        if ($capture_transaction) {
          commerce_amazon_lpa_transaction_message_update_data($capture_transaction, 'Authorization', $data['AuthorizationStatus']);
          $capture_transaction->payload[REQUEST_TIME . '-authorization'] = $data;
          commerce_payment_transaction_save($capture_transaction);
        }
      }
      elseif ($transaction) {
        $data = $api->getAuthorizationDetails($transaction->remote_id);
        $api->processAuthorizeTransaction($transaction, $data);

        // If working in manual or non-sync, check if we should change the
        // order status to the Authorized status configured.
        if (AmazonLPA::get_authorization_mode() != AmazonLPA::AUTH_SYNC) {
          $order = commerce_order_load($transaction->order_id);
          if (!empty($order->data['commerce_amazon_lpa_set_as_auth'])) {
            $authorized_order_status = variable_get('commerce_amazon_lpa_auth_order_status', 'pending');
            unset($order->data['commerce_amazon_lpa_set_as_auth']);
            commerce_order_status_update($order, $authorized_order_status, FALSE, TRUE, t('Payment with Amazon was authorized, moving to proper status.'));
          }
        }
      }
      else {
        watchdog('commerce_amazon_lpa', 'Unable to find matching payment transaction authorization for @id', array('@id' => $data['AmazonAuthorizationId']), WATCHDOG_ERROR);
      }
      break;

    case 'PaymentCapture':
    case 'CaptureNotification':
      $data = $ipn_message['CaptureDetails'];

      // Try to load the transaction first, the ID will have been updated if it
      // was captured / triggered in the UI.
      $transaction = commerce_amazon_lpa_remote_payment_transaction_load($data['AmazonCaptureId']);

      // If we have no transaction, we must look up the matching authorization
      // remote ID.
      if (!$transaction) {
        $id_components = explode('-', $data['AmazonCaptureId']);
        $id_components[3] = str_replace('C', 'A', $id_components[3]);
        $authorization_id = implode('-', $id_components);
        $transaction = commerce_amazon_lpa_remote_payment_transaction_load($authorization_id);
      }

      if ($transaction) {
        $api->processCaptureTransaction($transaction, $data);
      }
      else {
        watchdog('commerce_amazon_lpa', 'Unable to find matching payment transaction capture for @id', array('@id' => $data['AmazonCaptureId']), WATCHDOG_ERROR);
      }

      break;

    case 'PaymentRefund':
    case 'RefundNotification':
      $data = $ipn_message['RefundDetails'];
      $transaction = commerce_amazon_lpa_remote_payment_transaction_load($data['AmazonRefundId']);
      if ($transaction) {
        $api->processRefundTransaction($transaction, $data);
      }
      else {
        watchdog('commerce_amazon_lpa', 'Unable to find matching payment transaction refund for @id', array('@id' => $data['AmazonRefundId']), WATCHDOG_ERROR);
      }
      break;

    default:
      commerce_amazon_lpa_add_debug_log('Amazon IPN debug: IPN case did not match for @type', array(
        '@type' => $ipn_message['NotificationType'],
      ));
      break;
  }

}

/**
 * Page callback for Amazon Login.
 *
 * Verifies the access token and logs the user in as an external user.
 */
function commerce_amazon_lpa_login_callback() {
  if (variable_get('commerce_amazon_lpa_popup', 'popup') != 'popup' && !isset($_COOKIE['amazon_Login_accessToken'])) {
    return array(
      '#attached' => array(
        'library' => array(array('commerce_amazon_lpa', 'amazon_widgets')),
        'js' => array(
          drupal_get_path('module', 'commerce_amazon_lpa') . '/js/commerce-amazon-lpa-redirect.js',
        ),
      ),
    );
  }
  else {
    commerce_amazon_lpa_external_login();
    $cookie = $_COOKIE['amazon_Login_accessToken'];
    setrawcookie('amazon_Login_accessToken', $cookie, 0, '/', '', TRUE);
    drupal_goto('user');
  }
}
