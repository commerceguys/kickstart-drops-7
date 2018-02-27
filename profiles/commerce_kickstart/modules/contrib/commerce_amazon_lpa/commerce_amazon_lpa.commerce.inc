<?php

/**
 * @file
 * Drupal Commerce specific hook implementations.
 */

/**
 * Implements hook_commerce_order_status_info().
 */
function commerce_amazon_lpa_commerce_order_status_info() {
  $order_statuses['awaiting_auth'] = array(
    'name' => 'awaiting_auth',
    'title' => t('Awaiting Authorization'),
    'state' => 'pending',
    'weight' => -10,
  );
  return $order_statuses;
}

/**
 * Implements hook_commerce_checkout_pane_info().
 */
function commerce_amazon_lpa_commerce_checkout_pane_info() {
  $checkout_panes = array();
  $checkout_panes['commerce_amazon_lpa_contract_id'] = array(
    'title' => '',
    'name' => t('Amazon order contract negotiation'),
    'base' => 'commerce_amazon_lpa_contract_id_pane',
    'file' => 'includes/commerce_amazon_lpa.checkout_pane.inc',
    'page' => 'checkout',
    'fieldset' => FALSE,
    // Needs to be earlier so order contract ID is set.
    'weight' => -20,
  );

  $checkout_panes['commerce_amazon_lpa_switch_checkout'] = array(
    'title' => t('Checkout with Amazon'),
    'name' => t('Amazon order switch checkout'),
    'base' => 'commerce_amazon_lpa_switch_checkout_pane',
    'file' => 'includes/commerce_amazon_lpa.checkout_pane.inc',
    'page' => 'checkout',
    'fieldset' => FALSE,
    // Needs to be earlier so order contract ID is set.
    'weight' => -30,
  );

  if (module_exists('commerce_shipping')) {
    $checkout_panes['commerce_amazon_lpa_addressbook'] = array(
      'title' => t('Shipping information'),
      'name' => t('Amazon address book'),
      'base' => 'commerce_amazon_lpa_addressbook_pane',
      'file' => 'includes/commerce_amazon_lpa.checkout_pane.inc',
      'page' => 'checkout',
      'review' => TRUE,
      'weight' => 25,
    );
  }
  return $checkout_panes;
}

/**
 * Implements hook_commerce_checkout_pane_info_alter().
 */
function commerce_amazon_lpa_commerce_checkout_pane_info_alter(&$checkout_panes) {
  if (module_exists('commerce_shipping')) {
    $checkout_panes['commerce_shipping']['callbacks']['checkout_form'] = 'commerce_amazon_lpa_commerce_shipping_pane_checkout_form';
  }
}


/**
 * Implements hook_commerce_payment_method_info().
 *
 * Defines Checkout by Amazon payment method.
 */
function commerce_amazon_lpa_commerce_payment_method_info() {
  $payment_methods['commerce_amazon_login_and_pay'] = array(
    'title' => t('Amazon Pay'),
    'description' => t('Integration with Amazon Pay payment method'),
    'active' => TRUE,
    'checkout' => TRUE,
    'terminal' => FALSE,
    // We mark the module as an offsite so that payment method selection
    // can happen at any point during checkout.
    'offsite' => TRUE,
    'offsite_autoredirect' => FALSE,
    'file' => 'includes/commerce_amazon_lpa.payment_method.inc',
  );
  return $payment_methods;
}

/**
 * Implements hook_commerce_checkout_complete().
 */
function commerce_amazon_lpa_commerce_checkout_complete($order) {
  if (!empty($order->data['commerce_amazon_lpa'])) {
    $wrapper = entity_metadata_wrapper('commerce_order', $order);

    // Set the order reference and confirm it.
    $api = AmazonLPA::instance();
    $data = $api->getOrderRef($wrapper);

    $order->data['commerce_amazon_lpa']['order_reference'] = $data;

    // Also ensure our shipping profile is up to date.
    if (isset($data['Destination'])) {
      $amazon_shipping_address = $data['Destination']['PhysicalDestination'];
      commerce_amazon_lpa_amazon_address_to_customer_profile($order, 'shipping', $amazon_shipping_address);
    }

    commerce_order_save($order);
  }
}

/**
 * Implements hook_commerce_order_update().
 */
function commerce_amazon_lpa_commerce_order_presave($order) {
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
  $contract_id = $order_wrapper->{AmazonLPA::REFERENCE_ID_FIELD}->value();

  // If this is not an Amazon order, do not react.
  if (!$contract_id) {
    return;
  }

  $api = AmazonLPA::instance();

  /** @var EntityDrupalWrapper $order_wrapper */
  // First check if the order status has changed.
  if ($order->original->status != $order->status) {
    // Cancel the Amazon order reference if the order is canceled.
    if ($order->status == 'canceled') {
      try {
        $order_reference = $api->getOrderRef($order_wrapper);
        $amazon_order_state = $order_reference['OrderReferenceStatus']['State'];
        if ($amazon_order_state != 'Closed' && $amazon_order_state != 'Canceled') {
          $response = $api->cancel($order_wrapper);
          commerce_amazon_lpa_add_debug_log(t('Order cancel response: !debug', array('!debug' => '<pre>' . print_r($response, TRUE) . '</pre>')));
        }
      }
      catch (AmazonApiException $e) {
        drupal_set_message($e->getMessage(), 'error');
      }
    }

    // We're in ERP mode and aren't doing any additional processing.
    if (AmazonLPA::is_erp_mode()) {
      return;
    }

    $is_shipped_order_status = (variable_get('commerce_amazon_lpa_shipped_order_status') == $order->status);

    if ($is_shipped_order_status) {
      commerce_amazon_lpa_add_debug_log('Order @number shipped, closing Amazon order reference', array(
        '@number' => $order->order_id,
      ));

      try {
        $api->closeOrderRef($order_wrapper);
        // No need to save since the event will take care of that for us.
        $order->data['commerce_amazon_lpa']['order_details'] = AmazonLPA::instance()->getOrderRef($order_wrapper);
      }
      catch (Exception $e) {
        // The order was already closed remotely.
        return NULL;
      }
    }

    // If set to capture on shipment, do the capture if the status meets the
    // configured shipping status.
    $balance = commerce_payment_order_balance($order);
    if (AmazonLPA::get_capture_mode() == AmazonLPA::CAPTURE_SHIPMENT_CAPTURE && $is_shipped_order_status && $balance['amount'] > 0) {
      $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
      $transaction = commerce_amazon_lpa_get_order_authorization($order->order_id);
      try {
        if (!$transaction) {
          throw new Exception(t('There are no open authorizations, cannot capture the order payment.'));
        }
        $response = $api->capture($order_wrapper, $transaction->remote_id);

        $api->processCaptureTransaction($transaction, $response);

        if ($transaction->status == COMMERCE_PAYMENT_STATUS_SUCCESS) {
          drupal_set_message(t('The capture has been completed successfully.'));
        }
        elseif ($transaction->status == COMMERCE_PAYMENT_STATUS_PENDING) {
          drupal_set_message(t('The capture has been submitted successfully and may take a moment to process.'));
        }
        else {
          drupal_set_message(t('The capture was not successful.'), 'error');
        }

        commerce_amazon_lpa_add_debug_log(t('Order capture response !debug', array('!debug' => '<pre>' . print_r($response, TRUE) . '</pre>')));
      }
      catch (Exception $e) {
        $order->status = $order->original->status;
        drupal_set_message($e->getMessage(), 'error');

        // Something went wrong, try to refresh the authorization.
        if ($transaction) {
          $data = $api->getAuthorizationDetails($transaction->remote_id);
          $api->processAuthorizeTransaction($transaction, $data);
        }
      }
    }
  }

}

/**
 * Implements hook_commerce_product_type_insert().
 */
function commerce_amazon_lpa_commerce_product_type_insert($product_type, $skip_reset) {
  commerce_amazon_lpa_restricted_product_create_instance($product_type['type']);
}

/**
 * Implements hook_ENTITY_TYPE_view().
 */
function commerce_amazon_lpa_commerce_order_view($entity, $view_mode, $langcode) {
  // The US region does not support retrieval of billing information.
  // There is always a stubbed profile, so we need to hide it.
  if (variable_get('commerce_amazon_lpa_region', 'US') == 'US') {
    if (isset($entity->commerce_customer_billing)) {
      $entity->content['commerce_customer_billing']['#access'] = FALSE;
    }
  }
  if (!commerce_amazon_lpa_order_is_shippable($entity)) {
    if (isset($entity->commerce_customer_shipping)) {
      $entity->content['commerce_customer_shipping']['#access'] = FALSE;
    }
  }
}

/**
 * Implements hook_commerce_line_item_summary_link_info().
 */
function commerce_amazon_lpa_commerce_line_item_summary_link_info() {
  $cache = &drupal_static(__FUNCTION__, array());

  if (empty($cache)) {
    // Use a text title in the Views Line item summary handler, Links option.
    if (path_is_admin(current_path())) {
      $title = t('Checkout by Amazon button');
    }
    // Use Checkout by Amazon button in the Shopping cart block display.
    else {
      $order = commerce_cart_order_load($GLOBALS['user']->uid);
      if ($order) {
        $title = theme('commerce_amazon_payment_button__summary_link', array(
          'order_id' => $order->order_id,
          'html_id' => drupal_html_id('amazon_checkout_summary_link'),
          'button_options' => array(
            'payType' => 'Pay',
            'paySize' => 'small',
          ),
        ));
      }
      else {
        $title = '';
      }
    }

    $cache = array(
      'checkout_lpa' => array(
        'title' => $title,
        'attributes' => array('rel' => 'nofollow'),
        'weight' => 10,
        'html' => TRUE,
        'access' => user_access('access checkout') && !AmazonLPA::is_hidden(),
      ),
    );
  }

  return $cache;
}

/**
 * Implements hook_commerce_payment_order_paid_in_full().
 */
function commerce_amazon_lpa_commerce_payment_order_paid_in_full($order, $transaction) {
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
  $contract_id = $order_wrapper->{AmazonLPA::REFERENCE_ID_FIELD}->value();

  // If this is not an Amazon order, do not react.
  if (!$contract_id) {
    return;
  }

  $api = AmazonLPA::instance();

  try {
    $order_reference = $api->getOrderRef($order_wrapper);
    $amazon_order_state = $order_reference['OrderReferenceStatus']['State'];
    if ($amazon_order_state != 'Closed') {
      $response = $api->closeOrderRef($order_wrapper);
      commerce_amazon_lpa_add_debug_log(t('Order closed response: !debug', array('!debug' => '<pre>' . print_r($response, TRUE) . '</pre>')));
    }
  }
  catch (Exception $e) {
    drupal_set_message($e->getMessage(), 'error');
  }
}
