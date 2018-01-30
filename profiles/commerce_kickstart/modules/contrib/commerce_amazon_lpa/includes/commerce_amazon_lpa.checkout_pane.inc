<?php

/**
 * @file
 * Checkout pane implementation file.
 */

/**
 * Checkout pane callback: builds a shipping quote selection form.
 */
function commerce_amazon_lpa_contract_id_pane_checkout_form($form, &$form_state, $checkout_pane, $order) {
  $pane_form = array();

  if (empty($order->data['commerce_amazon_lpa'])) {
    return $pane_form;
  }

  $existing_reference_id = AmazonLPA::instance()->getOrderReferenceId(entity_metadata_wrapper('commerce_order', $order));
  $pane_form['reference_id'] = array(
    '#type' => 'hidden',
    '#default_value' => $existing_reference_id,
    '#access' => empty($existing_reference_id),
  );
  $pane_form['#attached']['js'][] = array(
    'data' => array(
      'AmazonLPA' => array(
        'orderReferenceId' => $existing_reference_id,
      ),
    ),
    'type' => 'setting',
  );

  return $pane_form;
}

/**
 * Checkout pane callback: builds a shipping quote selection form.
 */
function commerce_amazon_lpa_contract_id_pane_checkout_form_validate($form, &$form_state, $checkout_pane, $order) {
  if (empty($order->data['commerce_amazon_lpa'])) {
    return TRUE;
  }

  $pane_values = $form_state['values']['commerce_amazon_lpa_contract_id'];

  if (!empty($pane_values['reference_id'])) {
    /** @var EntityDrupalWrapper $order_wrapper */
    $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
    // Set the order.
    $order_wrapper->{AmazonLPA::REFERENCE_ID_FIELD} = $pane_values['reference_id'];

    try {
      $order->data['commerce_amazon_lpa']['order_reference'] = AmazonLPA::instance()->getOrderRef($order_wrapper);
    }
    catch (Exception $e) {
      watchdog('commerce_amazon_lpa', 'Error processing order for Amazon: !error', array('!error' => '<pre>' . print_r($e, TRUE) . '</pre>'), WATCHDOG_ERROR);
      form_set_error('', t('There was a problem with your checkout process. Please contact the store for further information.'));
      return FALSE;
    }

    $order_wrapper->save();

    $form_state['order'] = $order_wrapper->value();
    return TRUE;
  }

  // If the order is shippable, we need to have a valid contract ID from the
  // addressbook widget.
  if (commerce_amazon_lpa_order_is_shippable($order)) {
    form_set_error('', t('There was a problem with your checkout process. Please contact the store for further information.'));
    return FALSE;
  }

  return TRUE;
}

/**
 * Checkout pane callback: builds a shipping quote selection form.
 */
function commerce_amazon_lpa_switch_checkout_pane_checkout_form($form, &$form_state, $checkout_pane, $order) {
  $pane_form = array();

  if (!empty($order->data['commerce_amazon_lpa'])) {
    return $pane_form;
  }

  $pane_form['switch_button'] = array(
    '#markup' => theme('commerce_amazon_payment_button', array(
      'order_id' => $form_state['order']->order_id,
      'html_id' => 'amazon_lpa_cart_pay',
    )),
  );

  return $pane_form;
}

/**
 * Checkout pane callback: builds a shipping quote selection form.
 */
function commerce_amazon_lpa_addressbook_pane_checkout_form($form, &$form_state, $checkout_pane, $order) {
  $pane_form = array();

  if (!empty($order->data['commerce_amazon_lpa']) && commerce_amazon_lpa_order_is_shippable($order)) {
    // Build the checkout pane form.
    $pane_form['amazon_addressbook_widget'] = array(
      '#markup' => theme('commerce_amazon_addressbook_widget', array(
        'html_id' => 'checkout_amazon_addressbook',
      )),
    );
  }

  return $pane_form;
}

/**
 * Checkout pane callback: builds review pane.
 */
function commerce_amazon_lpa_addressbook_pane_review($form, &$form_state, $checkout_pane, $order) {
  if (!empty($order->data['commerce_amazon_lpa']) && commerce_amazon_lpa_order_is_shippable($order)) {
    return theme('commerce_amazon_addressbook_widget', array(
      'html_id' => 'customer_profile_shipping',
      'display_mode' => 'read',
    ));
  }

  return NULL;
}

/**
 * Checkout pane callback: validates a customer profile edit form.
 */
function commerce_amazon_lpa_addressbook_pane_checkout_form_validate($form, &$form_state, $checkout_pane, $order) {
  if (empty($order->data['commerce_amazon_lpa']) || !commerce_amazon_lpa_order_is_shippable($order)) {
    return TRUE;
  }

  /** @var EntityDrupalWrapper $order_wrapper */
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
  $data = AmazonLPA::instance()->getOrderRef($order_wrapper);
  // Strip off GetOrderReferenceDetailsResult.
  if (isset($data['Destination']['PhysicalDestination'])) {
    // Store the Amazon shipping address in form state for submit handler.
    $form_state['amazon_shipping_address'] = $data['Destination']['PhysicalDestination'];
    return TRUE;
  }

  // There was no physical destination set on the order.
  form_set_error(NULL, t('You must provide a shipping address'));
  return FALSE;
}

/**
 * Checkout pane callback: submit the Amazon address checkout pane.
 *
 * Mimic commerce_customer_profile_pane_checkout_form_submit() and build the
 * proper profile so we can calculate shipping properly.
 *
 * @see commerce_customer_profile_pane_checkout_form_submit()
 */
function commerce_amazon_lpa_addressbook_pane_checkout_form_submit(&$form, &$form_state, $checkout_pane, $order) {
  // Save the Amazon delivery address to shipping customer profile address.
  if (!empty($order->data['commerce_amazon_lpa']) && commerce_amazon_lpa_order_is_shippable($order)) {
    /** @var EntityDrupalWrapper $order_wrapper */
    $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
    $amazon_shipping_address = $form_state['amazon_shipping_address'];

    $pane_id = 'customer_profile_shipping';

    // If the shipping profile pane is present, inject our values.
    if (isset($form[$pane_id]['commerce_customer_address'])) {
      // Don't require address form elements.
      unset($form[$pane_id]['commerce_customer_address']['#required']);
      $customer_profile = $form[$pane_id]['customer_profile']['#value'];

      $form_state['values'][$pane_id]['commerce_customer_address'][LANGUAGE_NONE][0] = array(
        'country' => $amazon_shipping_address['CountryCode'],
        'name_line' => isset($amazon_shipping_address['Name']) ? $amazon_shipping_address['Name'] : '',
        'postal_code' => isset($amazon_shipping_address['PostalCode']) ? $amazon_shipping_address['PostalCode'] : '',
        'locality' => isset($amazon_shipping_address['City']) ? $amazon_shipping_address['City'] : '',
        'administrative_area' => isset($amazon_shipping_address['StateOrRegion']) ? $amazon_shipping_address['StateOrRegion'] : '',
        'data' => serialize($amazon_shipping_address),
      );
      commerce_unrequire_form_elements($form[$pane_id]);
      field_attach_submit('commerce_customer_profile', $customer_profile, $form[$pane_id], $form_state);
      $customer_profile->commerce_customer_address = $form_state['values'][$pane_id]['commerce_customer_address'];
      // Save the customer profile.
      commerce_customer_profile_save($customer_profile);

      // If Addressbook is enabled, make sure this is the default.
      // This really only matters on instances like CK2 that display the
      // information directly to the user and they may see the original
      // partial profile before the order is confirmed.
      if (module_exists('commerce_addressbook')) {
        commerce_addressbook_set_default_profile($customer_profile);
      }
    }
    // Write the profile ourself.
    else {
      $customer_profile = commerce_amazon_lpa_amazon_address_to_customer_profile($order, 'shipping', $amazon_shipping_address);
    }

    if ($field_name = variable_get('commerce_' . $pane_id . '_field', '')) {
      $order_wrapper->{$field_name} = $customer_profile;
    }
    else {
      // Make the association in the order's data array if no field was found.
      $order->data['profiles'][$pane_id] = $customer_profile->profile_id;
    }

    // Save the order with address data.
    commerce_order_save($order);
  }
}
