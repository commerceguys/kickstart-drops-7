<?php

/**
 * @file
 * Rules integration for Commerce Amazon LPA.
 *
 * @addtogroup rules
 * @ {
 */

/**
 * Implements hook_rules_event_info().
 */
function commerce_amazon_lpa_rules_event_info() {
  $variables = array_merge(
    entity_rules_events_variables('commerce_order', t('Order', array(), array('context' => 'a drupal commerce order')), TRUE),
    entity_rules_events_variables('commerce_payment_transaction', t('Last completed transaction'), TRUE),
    array('data' => array('type' => 'struct', 'label' => t('Data returned by Amazon')))
  );

  $events['commerce_amazon_lpa_nonsync_auth_soft_decline'] = array(
    'label' => t('When an Amazon Payments transaction has a soft decline'),
    'group' => t('Commerce Amazon LPA'),
    'variables' => $variables,
    'access callback' => 'commerce_order_rules_access',
  );

  $events['commerce_amazon_lpa_nonsync_auth_hard_decline'] = array(
    'label' => t('When an Amazon Payments transaction has a hard decline'),
    'group' => t('Commerce Amazon LPA'),
    'variables' => $variables,
    'access callback' => 'commerce_order_rules_access',
  );

  $events['commerce_amazon_lpa_authentication'] = array(
    'label' => t('A user authenticated an existing account with Amazon Login and Pay'),
    'group' => t('Commerce Amazon LPA'),
    'variables' => array(
      'user' => array('type' => 'user', 'label' => t('The user logging in with Amazon LPA')),
    ),
    'access callback' => 'commerce_order_rules_access',
  );

  $events['commerce_amazon_lpa_registration'] = array(
    'label' => t('A user registered a new account with Amazon Login and Pay'),
    'group' => t('Commerce Amazon LPA'),
    'variables' => array(
      'user' => array('type' => 'user', 'label' => t('The user logging in with Amazon LPA')),
    ),
    'access callback' => 'commerce_order_rules_access',
  );
  return $events;
}

/**
 * Implements hook_rules_condition_info().
 */
function commerce_amazon_lpa_rules_condition_info() {
  return array(
    'commerce_amazon_lpa_is_amazon_order' => array(
      'label' => t('Is an Amazon Payments order'),
      'parameter' => array(
        'commerce_order' => array(
          'type' => 'commerce_order',
          'label' => t('Order'),
          'description' => t('The order to be checked.'),
        ),
      ),
      'group' => t('Commerce Amazon Login & Pay'),
      'callbacks' => array(
        'execute' => 'commerce_amazon_lpa_is_amazon_order',
      ),
    ),
  );
}

/**
 * Condition callback for commerce_amazon_lpa_is_amazon_order
 *
 * @param $order
 *   The order to check.
 *
 * @return bool
 *   Returns TRUE if the order is for Amazon Payments.
 */
function commerce_amazon_lpa_is_amazon_order($order) {
  return !empty($order->data['commerce_amazon_lpa']);
}

/**
 * Implements hook_rules_action_info().
 */
function commerce_amazon_lpa_rules_action_info() {
  return array(
    'commerce_amazon_lpa_set_order_status' => array(
      'label' => t('Set the status based on Amazon order reference status.'),
      'parameter' => array(
        'commerce_order' => array(
          'type' => 'commerce_order',
          'label' => t('Order to update'),
        ),
      ),
      'group' => t('Commerce Amazon Login & Pay'),
      'callbacks' => array(
        'execute' => 'commerce_amazon_lpa_set_order_status',
      ),
    ),
  );
}

/**
 * Rules action: updates an order's status using the Order API.
 */
function commerce_amazon_lpa_set_order_status($order, $name) {
  $data = $order->data['commerce_amazon_lpa']['order_reference'];
  if (AmazonLPA::get_authorization_mode() == AmazonLPA::AUTH_MANUAL) {
    commerce_order_status_update($order, 'awaiting_auth', FALSE, TRUE, t('Amazon set to manual authorization, order placed in "Awaiting Authorization" status.'));
  }
  elseif (!empty($order->data['commerce_amazon_lpa_set_as_auth'])) {
    $authorized_order_status = variable_get('commerce_amazon_lpa_auth_order_status', 'pending');
    unset($order->data['commerce_amazon_lpa_set_as_auth']);
    commerce_order_status_update($order, $authorized_order_status, FALSE, TRUE, t('Payment with Amazon was authorized, moving to proper status.'));
  }
  // Check if the order has been paid in full (sync+immediate capture) and
  // mark the order closed properly.
  if ($data['OrderReferenceStatus']['State'] == 'Closed') {
    commerce_order_status_update($order, 'completed', TRUE, TRUE, t('Checkout set to sync and immediate capture, marking order as completed.'));
  }
}

/**
 * Implements hook_default_rules_configuration_alter().
 */
function commerce_amazon_lpa_default_rules_configuration_alter(&$configs) {
  if (isset($configs['commerce_payment_commerce_amazon_login_and_pay'])) {
    /** @var RulesReactionRule $rule */
    $rule =& $configs['commerce_payment_commerce_amazon_login_and_pay'];
    $rule->condition(rules_condition('commerce_amazon_lpa_is_amazon_order'));
  }
}

/**
 * @}
 */
