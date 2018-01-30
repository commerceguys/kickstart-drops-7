<?php

/**
 * @file
 * Admin interface implementations for Commerce Amazon LPA.
 */

/**
 * Settings form generator.
 *
 * @param array $form
 *   Form array.
 * @param array $form_state
 *   Form state array.
 *
 * @return mixed
 *   Form array.
 *
 * @throws \Exception
 *   If there is an Amazon exception.
 */
function commerce_amazon_lpa_settings_form(array $form, array &$form_state) {
  // Notification to enable the corresponding currency for the Merchant account.
  if (!in_array(commerce_default_currency(), array_values(AmazonLPA::get_region_currency_code()))) {
    drupal_set_message(t('You should have USD, EUR or GBP currency enabled to use Amazon Pay.'));
    $content = t('Please enable your Amazon Merchant account currency: !currency_page.', array('!currency_page' => l(t('Currency settings page'), 'admin/commerce/config/currency')));
    $help_items[] = t('EUR for the German account.');
    $help_items[] = t('GBP for the UK account.');
    $help_items[] = t('USD for the United States account.');
    $content .= theme('item_list', array('items' => $help_items));
    $form['content']['#markup'] = $content;
    return $form;
  }

  $form['commerce_amazon_lpa_account_configuration'] = array(
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
    '#title' => t('Account configuration'),
  );

  // Checkout by Amazon settings form.
  $form['commerce_amazon_lpa_account_configuration']['commerce_amazon_lpa_merchant_id'] = array(
    '#type' => 'textfield',
    '#title' => t('Merchant ID'),
    '#default_value' => variable_get('commerce_amazon_lpa_merchant_id', ''),
    '#required' => TRUE,
  );
  $form['commerce_amazon_lpa_account_configuration']['commerce_amazon_lpa_access_key'] = array(
    '#type' => 'textfield',
    '#title' => t('MWS Access key'),
    '#default_value' => variable_get('commerce_amazon_lpa_access_key', ''),
    '#required' => TRUE,
  );
  $form['commerce_amazon_lpa_account_configuration']['commerce_amazon_lpa_secret_key'] = array(
    '#type' => 'password',
    '#title' => t('MWS Secret key'),
    '#default_value' => variable_get('commerce_amazon_lpa_secret_key', ''),
    '#required' => (variable_get('commerce_amazon_lpa_secret_key') === NULL),
    '#description' => variable_get('commerce_amazon_lpa_secret_key') ? t('Secret key has been set. Leave blank unless you want to change the saved key.') : NULL,
  );
  $form['commerce_amazon_lpa_account_configuration']['commerce_amazon_lpa_region'] = array(
    '#type' => 'radios',
    '#title' => t('Region'),
    '#options' => array(
      'DE' => t('Germany'),
      'UK' => t('United Kingdom'),
      'US' => t('United States'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_region', 'US'),
    '#required' => TRUE,
  );
  $form['commerce_amazon_lpa_account_configuration']['commerce_amazon_lpa_client_id'] = array(
    '#type' => 'textfield',
    '#title' => t('LWA Client ID'),
    '#default_value' => variable_get('commerce_amazon_lpa_client_id', ''),
    '#required' => TRUE,
  );

  $form['commerce_amazon_lpa_general_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('General settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['commerce_amazon_lpa_general_settings']['commerce_amazon_lpa_operation_mode'] = array(
    '#type' => 'radios',
    '#title' => t('Operation mode'),
    '#description' => t('Provide Amazon Pay and Login with Amazon or just Amazon Pay. Using Login with Amazon links Drupal user accounts with the Amazon account'),
    '#options' => array(
      AmazonLPA::OPERATION_MODE_LOGIN_AND_PAY => t('Amazon Pay and Login with Amazon'),
      AmazonLPA::OPERATION_MODE_PAY_ONLY => t('Amazon Pay only'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_operation_mode', 'login_pay'),
  );
  $form['commerce_amazon_lpa_general_settings']['commerce_amazon_lpa_environment'] = array(
    '#type' => 'radios',
    '#title' => t('Environment'),
    '#options' => array(AmazonLPA::ENV_SANDBOX => t('Sandbox'), AmazonLPA::ENV_LIVE => t('Live')),
    '#default_value' => variable_get('commerce_amazon_lpa_environment', AmazonLPA::ENV_SANDBOX),
  );
  $form['commerce_amazon_lpa_general_settings']['commerce_amazon_lpa_hidden_mode'] = array(
    '#type' => 'radios',
    '#title' => t('Hidden button mode'),
    '#description' => t('This allows you to keep the integration active, but only available to select roles.'),
    '#options' => array('' => 'Disabled') + user_roles(TRUE),
    '#default_value' => variable_get('commerce_amazon_lpa_hidden_mode', ''),
  );
  $form['commerce_amazon_lpa_general_settings']['commerce_amazon_lpa_langcode'] = array(
    '#type' => 'radios',
    '#title' => t('Checkout language'),
    '#options' => array(
      'en-US' => t('US English'),
      'en-GB' => t('UK English'),
      'de-DE' => t('German'),
      'fr-FR' => t('French'),
      'it-IT' => t('Italian'),
      'es-ES' => t('Spanish (Spain)'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_langcode', AmazonLPA::get_region_langcode(variable_get('commerce_amazon_lpa_region', ''))),
  );

  $form['commerce_amazon_lpa_checkout_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Checkout settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['commerce_amazon_lpa_checkout_settings']['commerce_amazon_lpa_checkout_strategy'] = array(
    '#type' => 'radios',
    '#title' => t('Checkout strategy'),
    '#description' => t('If the user has logged in using Login with Amazon, which checkout options are available?'),
    '#options' => array(
      AmazonLPA::STRATEGY_AMAZON => t('Only Amazon checkout'),
      AmazonLPA::STRATEGY_NORMAL => t('Normal checkout and Amazon checkout'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_checkout_strategy', AmazonLPA::STRATEGY_NORMAL),
  );
  $form['commerce_amazon_lpa_checkout_settings']['commerce_amazon_lpa_popup'] = array(
    '#type' => 'radios',
    '#title' => t('Use a redirect or popup for login'),
    '#description' => t("Integration can either use a pop up or redirect experience. You must add the following as <strong>Allowed Return URLs</strong> in your Login with Amazon application in Seller Central: <ul><li>!redirect1</li><li>!redirect2</li></ul>", array(
      '!redirect1' => '<code>' . url('checkout/amazon', array('absolute' => TRUE)) . '</code>',
      '!redirect2' => '<code>' . url('user/login/amazon', array('absolute' => TRUE, 'query' => array('amzn' => 'LwA'))) . '</code>',
    )),
    '#options' => array(
      'popup' => t('Popup'),
      'redirect' => t('Redirect'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_popup', 'popup'),
  );

  $form['commerce_amazon_lpa_payment_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Payment settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['commerce_amazon_lpa_payment_settings']['commerce_amazon_lpa_authorization_mode'] = array(
    '#type' => 'select',
    '#title' => t('Authorization mode'),
    '#options' => array(
      AmazonLPA::AUTH_SYNC => t('Automatic synchronous authorization in frontend'),
      AmazonLPA::AUTH_NONSYNC => t('Automatic non-synchronous after order is placed'),
      AmazonLPA::AUTH_MANUAL => t('Manual non-synchronous authorization through order management.'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_authorization_mode', 'automatic_nonsync'),
  );
  $form['commerce_amazon_lpa_payment_settings']['commerce_amazon_lpa_capture_mode'] = array(
    '#type' => 'select',
    '#title' => t('Capture mode'),
    '#options' => array(
      AmazonLPA::CAPTURE_AUTH_CAPTURE => t('Immediate capture on successful authorization'),
      AmazonLPA::CAPTURE_SHIPMENT_CAPTURE => t('Capture on shipment'),
      AmazonLPA::CAPTURE_MANUAL_CAPTURE => t('Manual capture through order management'),
    ),
    '#default_value' => AmazonLPA::get_capture_mode(),
    '#description' => t('Whitelisting with Amazon Pay is required for "Immediate capture on successful authorization"'),
  );
  $form['commerce_amazon_lpa_payment_settings']['commerce_amazon_lpa_capture_auth_statement'] = array(
    '#type' => 'textfield',
    '#title' => t('Authorization statement'),
    '#default_value' => variable_get('commerce_amazon_lpa_capture_auth_statement', ''),
    '#description' => t('This will appear at the bottom of your Amazon order notifications.'),
    '#states' => array(
      'visible' => array(
        ':input[name="commerce_amazon_lpa_capture_mode"]' => array('value' => AmazonLPA::CAPTURE_AUTH_CAPTURE),
      ),
    ),
  );

  switch (variable_get('commerce_amazon_lpa_region')) {
    case 'UK':
      $ipn_link = 'https://sellercentral.amazon.co.uk/gp/pyop/seller/account/settings/user-settings-view.html';
      break;

    case 'DE':
      $ipn_link = 'https://sellercentral.amazon.de/gp/pyop/seller/account/settings/user-settings-view.html';
      break;

    case 'US':
    default:
      $ipn_link = 'https://sellercentral.amazon.com/gp/pyop/seller/account/settings/user-settings-view.html';
      break;
  }
  $form['commerce_amazon_lpa_payment_settings']['commerce_amazon_lpa_payment_sync'] = array(
    '#type' => 'radios',
    '#title' => t('Method for synchronizing payment transactions'),
    '#description' => t("You may !ipn-link or use Drupal's cron to update payment transactions. Your Merchant URL for the IPN is !ipn-destination. <p><em>Note, if you use the IPN Amazon must be able to resolve your domain name.</em></p>", array(
      '!ipn-destination' => '<code>' . url('commerce-amazon-lpa/ipn', array('absolute' => TRUE)) . '</code>',
      '!ipn-link' => l(t('enable Instant Payment Notifications (IPN)'), $ipn_link),
    )),
    '#options' => array(
      'ipn' => t('IPN'),
      'cron' => t('Cron'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_payment_sync', 'ipn'),
  );
  $order_statuses = array();
  foreach (commerce_order_statuses() as $status => $data) {
    $order_statuses[$status] = $data['title'];
  }
  $form['commerce_amazon_lpa_payment_settings']['commerce_amazon_lpa_auth_order_status'] = array(
    '#type' => 'select',
    '#title' => t('Order status on successful authorization'),
    '#description' => t('Orders with a successful authorization will be in this status.'),
    '#options' => $order_statuses,
    '#default_value' => variable_get('commerce_amazon_lpa_auth_order_status', 'pending'),
  );
  $form['commerce_amazon_lpa_payment_settings']['commerce_amazon_lpa_shipped_order_status'] = array(
    '#type' => 'select',
    '#title' => t('Order status on successful shipping'),
    '#description' => t('If using the <em>Capture on shipment</em> capture mode, this status will trigger payment capture.'),
    '#options' => $order_statuses,
    '#default_value' => variable_get('commerce_amazon_lpa_shipped_order_status', 'completed'),
  );

  $form['commerce_amazon_lpa_appearance_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Appearance settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_pay_button_size'] = array(
    '#type' => 'select',
    '#title' => t('Pay button size'),
    '#options' => array(
      AmazonLPA::BUTTON_SIZE_SMALL => t('Small'),
      AmazonLPA::BUTTON_SIZE_MEDIUM => t('Medium'),
      AmazonLPA::BUTTON_SIZE_LARGE => t('Large'),
      AmazonLPA::BUTTON_SIZE_X_LARGE => t('Extra large'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_pay_button_size', AmazonLPA::BUTTON_SIZE_MEDIUM),
  );
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_pay_button_style'] = array(
    '#type' => 'select',
    '#title' => t('Pay button style'),
    '#options' => array(
      AmazonLPA::BUTTON_COLOR_GOLD => t('Gold'),
      AmazonLPA::BUTTON_COLOR_LIGHT_GRAY => t('Light gray'),
      AmazonLPA::BUTTON_COLOR_DARK_GRAY => t('Dark gray'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_pay_button_style', AmazonLPA::BUTTON_COLOR_GOLD),
  );
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_login_button_size'] = array(
    '#type' => 'select',
    '#title' => t('Login button size'),
    '#options' => array(
      AmazonLPA::BUTTON_SIZE_SMALL => t('Small'),
      AmazonLPA::BUTTON_SIZE_MEDIUM => t('Medium'),
      AmazonLPA::BUTTON_SIZE_LARGE => t('Large'),
      AmazonLPA::BUTTON_SIZE_X_LARGE => t('Extra large'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_login_button_size', AmazonLPA::BUTTON_SIZE_MEDIUM),
  );
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_login_button_style'] = array(
    '#type' => 'select',
    '#title' => t('Login button style'),
    '#options' => array(
      AmazonLPA::BUTTON_COLOR_GOLD => t('Gold'),
      AmazonLPA::BUTTON_COLOR_LIGHT_GRAY => t('Light gray'),
      AmazonLPA::BUTTON_COLOR_DARK_GRAY => t('Dark gray'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_login_button_style', AmazonLPA::BUTTON_COLOR_GOLD),
  );

  $addressbook_size = explode('x', variable_get('commerce_amazon_lpa_addressbook_size')) + array('0', '250');
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_addressbook_size'] = array(
    '#type' => 'item',
    '#title' => t('Addressbook widget default size'),
    '#element_validate' => array('_commerce_amazon_lpa_widget_size_validate'),
    '#subelement_prefix' => 'addressbook_',
    '#field_prefix' => '<div class="container-inline">',
    '#description' => t('Set the width to 0 for full width'),
    '#field_suffix' => '</div>',
  );
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_addressbook_size']['addressbook_x'] = array(
    '#type' => 'textfield',
    '#title' => t('Width'),
    '#title_display' => 'invisible',
    '#default_value' => $addressbook_size[0] ? $addressbook_size[0] : 0,
    '#size' => 5,
    '#maxlength' => 5,
    '#field_suffix' => ' x ',
  );
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_addressbook_size']['addressbook_y'] = array(
    '#type' => 'textfield',
    '#title' => t('Height'),
    '#title_display' => 'invisible',
    '#default_value' => $addressbook_size[1],
    '#size' => 5,
    '#maxlength' => 5,
    '#field_suffix' => ' ' . t('pixels'),
  );

  $wallet_size = explode('x', variable_get('commerce_amazon_lpa_wallet_size')) + array('0', '250');
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_wallet_size'] = array(
    '#type' => 'item',
    '#title' => t('Wallet default size'),
    '#element_validate' => array('_commerce_amazon_lpa_widget_size_validate'),
    '#subelement_prefix' => 'wallet_',
    '#weight' => 4.1,
    '#field_prefix' => '<div class="container-inline">',
    '#description' => t('Set the width to 0 for full width'),
    '#field_suffix' => '</div>',
  );
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_wallet_size']['wallet_x'] = array(
    '#type' => 'textfield',
    '#title' => t('Maximum width'),
    '#title_display' => 'invisible',
    '#default_value' => $wallet_size[0] ? $wallet_size[0] : 0,
    '#size' => 5,
    '#maxlength' => 5,
    '#field_suffix' => ' x ',
  );
  $form['commerce_amazon_lpa_appearance_settings']['commerce_amazon_lpa_wallet_size']['wallet_y'] = array(
    '#type' => 'textfield',
    '#title' => t('Maximum height'),
    '#title_display' => 'invisible',
    '#default_value' => $wallet_size[1],
    '#size' => 5,
    '#maxlength' => 5,
    '#field_suffix' => ' ' . t('pixels'),
  );

  $form['commerce_amazon_lpa_advanced_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['commerce_amazon_lpa_advanced_settings']['commerce_amazon_lpa_digital_product_types'] = array(
    '#type' => 'select',
    '#title' => t('Digital product types'),
    '#description' => t('If you have digital products that are not shippable, select them. Any order that contains only digital products will not have shipping information collected'),
    '#options' => array('_none' => t('None')) + commerce_product_type_get_name(),
    '#multiple' => TRUE,
    '#default_value' => variable_get('commerce_amazon_lpa_digital_product_types', array('_none')),
  );
  $form['commerce_amazon_lpa_advanced_settings']['commerce_amazon_lpa_erp_mode'] = array(
    '#type' => 'select',
    '#title' => t('ERP Mode'),
    '#description' => t('Disables all automated authorization and capture functionality and administrative functionality that may trigger authorizations or captures.'),
    '#options' => array(
      0 => t('Disabled'),
      1 => t('Enabled'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_erp_mode', 0),
  );

  $simulation_options = array(
    '_none' => t('Disabled'),
  );
  $simulation_options['Authorizations - Declined'] = array(
    'Authorizations_InvalidPaymentMethod' => t('Invalid payment method'),
    'Authorizations_AmazonRejected' => t('Amazon rejected'),
    'Authorizations_TransactionTimedOut' => t('Transaction timed out'),
  );
  $simulation_options['Authorizations - Closed'] = array(
    'Authorizations_ExpiredUnused' => t('Expired, unused'),
    'Authorizations_AmazonClosed' => t('Amazon closed'),
  );
  $simulation_options['Captures'] = array(
    'Captures_Pending' => t('Pending'),
    'Captures_AmazonRejected' => t('Declined, Amazon rejected'),
    'Captures_AmazonClosed' => t('Closed, Amazon closed'),
  );
  $simulation_options['Order Reference - Closed'] = array(
    'OrderReference_AmazonClosed' => t('Amazon closed'),
  );
  $simulation_options['Refund'] = array(
    'Refund_AmazonRejected' => t('Declined, Amazon rejected'),
  );

  $form['commerce_amazon_lpa_advanced_settings']['commerce_amazon_lpa_simulation'] = array(
    '#title' => t('Sandbox simulation'),
    '#type' => 'select',
    '#description' => t('Simulate different scenarios for testing. See !link for more information', array(
      '!link' => l(t('this documentation'), 'https://payments.amazon.co.uk/developer/documentation/lpwa/201750790'),
    )),
    '#options' => $simulation_options,
    '#access' => AmazonLPA::is_sandbox(),
    '#default_value' => $simulation_code = variable_get('commerce_amazon_lpa_simulation', '_none'),
    '#tree' => TRUE,
  );

  $form['commerce_amazon_lpa_logging_settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Logging settings'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['commerce_amazon_lpa_logging_settings']['commerce_amazon_lpa_log_handling'] = array(
    '#type' => 'radios',
    '#title' => t('Log debugging information'),
    '#description' => t('Errors and exceptions will still be logged.'),
    '#options' => array(
      0 => t('Disabled'),
      1 => t('Enabled'),
    ),
    '#default_value' => variable_get('commerce_amazon_lpa_log_handling', 0),
  );
  $form['commerce_amazon_lpa_logging_settings']['commerce_amazon_lpa_log_link'] = array(
    '#markup' => l(t('View Amazon Pay and Login with Amazon logs'), 'admin/reports/dblog'),
  );

  $form = system_settings_form($form);
  return $form;
}

/**
 * Element validate function for amazon widgets.
 */
function _commerce_amazon_lpa_widget_size_validate($element, &$form_state) {
  $prefix = $element['#subelement_prefix'];
  if (!empty($element[$prefix . 'x']['#value']) || !empty($element[$prefix . 'y']['#value'])) {
    foreach (array('x', 'y') as $dimension) {
      $value = $element[$prefix . $dimension]['#value'];
      if (!is_numeric($value)) {
        form_error($element[$prefix . $dimension], t('Height and width values must be numeric.'));
        return;
      }
      if (intval($value) == 0 && $dimension == 'y') {
        form_error($element[$prefix . $dimension], t('Height value must be specified in the !name field.', array('!name' => $element['#title'])));
        return;
      }
    }
    form_set_value($element, intval($element[$prefix . 'x']['#value']) . 'x' . intval($element[$prefix . 'y']['#value']), $form_state);
  }
  else {
    form_set_value($element, '', $form_state);
  }
}

/**
 * Form validator.
 */
function commerce_amazon_lpa_settings_form_validate($form, &$form_state) {
  // Temporarily set the API credentials.
  global $conf;
  $conf['commerce_amazon_lpa_merchant_id'] = $form_state['values']['commerce_amazon_lpa_merchant_id'];
  $conf['commerce_amazon_lpa_access_key'] = $form_state['values']['commerce_amazon_lpa_access_key'];

  // Secret key is a password field and not required, meaning it could be empty.
  if (!empty($form_state['values']['commerce_amazon_lpa_secret_key'])) {
    $conf['commerce_amazon_lpa_secret_key'] = $form_state['values']['commerce_amazon_lpa_secret_key'];
  }
  else {
    // So we need to retain its value here.
    $form_state['values']['commerce_amazon_lpa_secret_key'] = $conf['commerce_amazon_lpa_secret_key'];
  }

  $conf['commerce_amazon_lpa_region'] = $form_state['values']['commerce_amazon_lpa_region'];

  try {
    // Validate API credentials against order reference S00-0000000-0000000.
    $api = AmazonLPA::instance();

    $params = array(
      'amazon_order_reference_id' => 'S00-0000000-0000000',
    );

    $response = $api->getClient()->getOrderReferenceDetails($params)->toArray();
    switch ($response['Error']['Code']) {
      case 'InvalidOrderReferenceId':
        // This is what we want, do not react.
        break;

      case 'InvalidAccessKeyId':
        form_set_error('commerce_amazon_lpa_access_key', $response['Error']['Message']);
        break;

      case 'SignatureDoesNotMatch':
        form_set_error('commerce_amazon_lpa_secret_key', $response['Error']['Message']);
        break;

      case 'InvalidParameterValue':
        form_set_error('commerce_amazon_lpa_merchant_id', $response['Error']['Message']);
        break;

      default:
        form_set_error('commerce_amazon_lpa_merchant_id', $response['Error']['Message']);
    }
  }
  catch (Exception $e) {
    form_set_error('commerce_amazon_lpa_merchant_id', t('Invalid API credentials'));
  }

  // Force the store to use a proper default currency.
  // @see commerce_order_calculate_total()
  $region_currency = AmazonLPA::get_region_currency_code(variable_get('commerce_amazon_lpa_region', 'US'));
  $default_currency = commerce_default_currency();
  if ($region_currency != $default_currency) {
    form_set_error('commerce_amazon_lpa_region', t('Your current default currency is @default, however your Amazon seller region requires @required.', array(
      '@default' => $default_currency,
      '@required' => $region_currency,
    )));
  }

  // If using hidden button mode and Amazon only checkout, display warning.
  if ($form_state['values']['commerce_amazon_lpa_checkout_strategy'] == AmazonLPA::STRATEGY_AMAZON
      && $form_state['values']['commerce_amazon_lpa_hidden_mode'] != '') {
    drupal_set_message(t('You have set the checkout strategy to Amazon only, but have Hidden Button Mode enabled. Customers might not be able to complete checkout.'), 'warning');
  }
}

/**
 * Form callback: allows the user to authorize a transaction on an order.
 */
function commerce_amazon_lpa_authorize_form($form, &$form_state, $order, $transaction = NULL) {
  $balance = commerce_payment_order_balance($order);

  if (!$transaction) {
    $transaction = commerce_payment_transaction_new('commerce_amazon_login_and_pay', $order->order_id);
    $transaction->amount = $balance['amount'];
    $transaction->currency_code = $balance['currency_code'];
  }

  $form_state['order'] = $order;
  $form_state['transaction'] = $transaction;

  // Load and store the payment method instance for this transaction.
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $form_state['payment_method'] = $payment_method;

  if ($balance['amount'] > 0 && $balance['amount'] < $transaction->amount) {
    $default_amount = $balance['amount'];
  }
  else {
    $default_amount = $transaction->amount;
  }

  // Convert the price amount to a user friendly decimal value.
  $default_amount = number_format(commerce_currency_amount_to_decimal($default_amount, $transaction->currency_code), 2, '.', '');

  $description = implode('<br />', array(
    t('Authorization: @amount', array('@amount' => commerce_currency_format($transaction->amount, $transaction->currency_code))),
    t('Order balance: @balance', array('@balance' => commerce_currency_format($balance['amount'], $balance['currency_code']))),
    t('You may capture up to the remaining order balance.'),
  ));

  $form['amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Capture amount'),
    '#description' => $description,
    '#default_value' => $default_amount,
    '#field_suffix' => check_plain($transaction->currency_code),
    '#size' => 16,
  );

  $form = confirm_form($form,
    t('What amount do you want to authorize?'),
    'admin/commerce/orders/' . $order->order_id . '/payment',
    '',
    t('Authorize'),
    t('Cancel'),
    'confirm'
  );

  return $form;
}

/**
 * Validate handler: ensure a valid amount is given.
 */
function commerce_amazon_lpa_authorize_form_validate($form, &$form_state) {
  $transaction = $form_state['transaction'];
  $amount      = $form_state['values']['amount'];

  $balance = commerce_payment_order_balance($form_state['order']);

  if ($balance['amount'] > 0 && $balance['amount'] < $transaction->amount) {
    $allowed_amount = $balance['amount'];
  }
  else {
    $allowed_amount = $transaction->amount;
  }

  // Ensure a positive numeric amount has been entered for capture.
  if (!is_numeric($amount) || $amount <= 0) {
    form_set_error('amount', t('You must specify a positive numeric amount to authorize.'));
  }

  if ($allowed_amount < $amount) {
    form_set_error('amount', t('That amount exceed the maximum allowed authorize amount.'));
  }
}

/**
 * Submit handler: process a prior authorization capture.
 */
function commerce_amazon_lpa_authorize_form_submit($form, &$form_state) {
  $transaction = $form_state['transaction'];
  $order = $form_state['order'];
  $api = AmazonLPA::instance();

  /** @var EntityDrupalWrapper $order_wrapper */
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);

  try {
    $data = $api->authorize($order_wrapper, AmazonLPA::get_capture_mode() == AmazonLPA::CAPTURE_AUTH_CAPTURE ? TRUE : FALSE, array(
      'amount' => commerce_currency_decimal_to_amount($form_state['values']['amount'], $transaction->currency_code),
      'currency_code' => $transaction->currency_code,
    ));
    commerce_amazon_lpa_add_debug_log(t('Terminal authorize: !debug', array('!debug' => '<pre>' . print_r($data, TRUE) . '</pre>')));

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

    // Create a revision, since we have changed the remote ID.
    $transaction->revision = TRUE;
    $transaction->log = t('Authorization was created');

    if ($transaction->status == COMMERCE_PAYMENT_STATUS_SUCCESS) {
      drupal_set_message(t('The authorization has been completed successfully.'));
    }
    elseif ($transaction->status == COMMERCE_PAYMENT_STATUS_PENDING) {
      drupal_set_message(t('The authorisations has been submitted successfully and may take a moment to process.'));
    }
    else {
      $status = $data['AuthorizationStatus'];
      switch ($status['ReasonCode']) {
        case 'AmazonRejected':
          $message = t('Your payment could not be processed. Please try to place the order again using another payment method.');
          commerce_order_status_update($order, 'cart', FALSE, TRUE, t('Order canceled because it was rejected by Amazon'));
          break;

        case 'TransactionTimedOut':
          $message = t('Your payment could not be processed. Please try to place the order again using another payment method.');
          commerce_order_status_update($order, 'cart', FALSE, TRUE, t('Order canceled because the transaction timed out.'));
          break;

        case 'ProcessingFailure':
          $message = t('Your order could not be processed due to a system error. Please try to place the order again.');
          commerce_order_status_update($order, 'cart', FALSE, TRUE, t('Order canceled because the transaction failed to process.'));
          break;

        case 'InvalidPaymentMethod':
        default:
          $message = t('Your payment could not be processed, please follow the instructions in the payment method box..');
          break;
      }
      drupal_set_message($message, 'error');
    }
  }
  catch (Exception $e) {
    drupal_set_message($e->getMessage(), 'error');
  }

  // Redirect back to the current order payment page.
  $form_state['redirect'] = 'admin/commerce/orders/' . $form_state['order']->order_id . '/payment';
}

/**
 * Form callback: allows the user to capture a prior authorization.
 */
function commerce_amazon_lpa_capture_form($form, &$form_state, $order, $transaction) {
  $form_state['order'] = $order;
  $form_state['transaction'] = $transaction;

  // Load and store the payment method instance for this transaction.
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $form_state['payment_method'] = $payment_method;

  $balance = commerce_payment_order_balance($order);

  if ($balance['amount'] > 0 && $balance['amount'] < $transaction->amount) {
    $default_amount = $balance['amount'];
  }
  else {
    $default_amount = $transaction->amount;
  }

  // Convert the price amount to a user friendly decimal value.
  $default_amount = number_format(commerce_currency_amount_to_decimal($default_amount, $transaction->currency_code), 2, '.', '');

  $description = implode('<br />', array(
    t('Authorization: @amount', array('@amount' => commerce_currency_format($transaction->amount, $transaction->currency_code))),
    t('Order balance: @balance', array('@balance' => commerce_currency_format($balance['amount'], $balance['currency_code']))),
    t('You may capture up to the remaining order balance.'),
  ));

  $form['amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Capture amount'),
    '#description' => $description,
    '#default_value' => $default_amount,
    '#field_suffix' => check_plain($transaction->currency_code),
    '#size' => 16,
  );

  $form = confirm_form($form,
    t('What amount do you want to capture?'),
    'admin/commerce/orders/' . $order->order_id . '/payment',
    '',
    t('Capture'),
    t('Cancel'),
    'confirm'
  );

  return $form;
}

/**
 * Validate handler: ensure a valid amount is given.
 */
function commerce_amazon_lpa_capture_form_validate($form, &$form_state) {
  $transaction = $form_state['transaction'];
  $amount      = $form_state['values']['amount'];

  $balance = commerce_payment_order_balance($form_state['order']);

  if ($balance['amount'] > 0 && $balance['amount'] < $transaction->amount) {
    $allowed_amount = $balance['amount'];
  }
  else {
    $allowed_amount = $transaction->amount;
  }

  // Ensure a positive numeric amount has been entered for capture.
  if (!is_numeric($amount) || $amount <= 0) {
    form_set_error('amount',
      t('You must specify a positive numeric amount to capture.'));
  }

  if ($allowed_amount < $amount) {
    form_set_error('amount',
      t('That amount exceed the maximum allowed capture amount.'));
  }

  // If the authorization has expired, display an error message and redirect.
  if (REQUEST_TIME - $transaction->created > 86400 * 30) {
    drupal_set_message(t('This authorization has passed its 30 day limit and cannot be captured.'), 'error');
    drupal_goto('admin/commerce/orders/' . $form_state['order']->order_id . '/payment');
  }
}

/**
 * Submit handler: process a prior authorization capture.
 */
function commerce_amazon_lpa_capture_form_submit($form, &$form_state) {
  $transaction = $form_state['transaction'];
  $order = $form_state['order'];
  $api = AmazonLPA::instance();

  /** @var EntityDrupalWrapper $order_wrapper */
  $order_wrapper = entity_metadata_wrapper('commerce_order', $order);

  try {
    $data = $api->capture($order_wrapper, $transaction->remote_id, array(
      'amount' => commerce_currency_decimal_to_amount($form_state['values']['amount'], $transaction->currency_code),
      'currency_code' => $transaction->currency_code,
    ));

    commerce_amazon_lpa_add_debug_log(t('Terminal capture: !debug', array('!debug' => '<pre>' . print_r($data, TRUE) . '</pre>')));

    // Create a revision, since we have changed the remote ID.
    $transaction->revision = TRUE;
    $transaction->log = t('Authorization was captured');

    // Create a transaction based on the original response we received.
    $api->processCaptureTransaction($transaction, $data);

    if ($transaction->status == COMMERCE_PAYMENT_STATUS_SUCCESS) {
      drupal_set_message(t('The capture has been completed successfully.'));
    }
    elseif ($transaction->status == COMMERCE_PAYMENT_STATUS_PENDING) {
      drupal_set_message(t('The capture has been submitted successfully and may take a moment to process.'));
    }
    else {
      drupal_set_message(t('The capture was not successful.'), 'error');
    }
  }
  catch (Exception $e) {
    drupal_set_message($e->getMessage(), 'error');

    // Something went wrong, try to refresh the authorization.
    $data = $api->getAuthorizationDetails($transaction->remote_id);
    $api->processAuthorizeTransaction($transaction, $data);
  }

  // Redirect back to the current order payment page.
  $form_state['redirect'] = 'admin/commerce/orders/' . $form_state['order']->order_id . '/payment';
}

/**
 * Form callback: allows the user to issue a credit on a prior transaction.
 */
function commerce_amazon_lpa_refund_form($form, &$form_state, $order, $transaction) {
  $form_state['order'] = $order;
  $form_state['transaction'] = $transaction;

  // Load and store the payment method instance for this transaction.
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $form_state['payment_method'] = $payment_method;
  $balance = commerce_payment_order_balance($order);

  $default_amount = number_format(commerce_currency_amount_to_decimal($transaction->amount, $transaction->currency_code), 2, '.', '');
  $max_refund = ($transaction->amount > 50000) ? $transaction->amount + 7500 :
    $transaction->amount * 1.15;

  $description = implode('<br />', array(
    t('Max allowed refund: @amount', array('@amount' => commerce_currency_format($max_refund, $transaction->currency_code))),
    t('Order balance: @balance', array('@balance' => commerce_currency_format($balance['amount'], $balance['currency_code']))),
    t('Original authorized amount: @original', array('@original' => commerce_currency_format($transaction->amount, $transaction->currency_code))),
  ));

  $form['amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Refund amount'),
    '#description' => $description,
    '#default_value' => $default_amount,
    '#field_suffix' => check_plain($transaction->currency_code),
    '#size' => 16,
  );

  $form['note'] = array(
    '#type' => 'textfield',
    '#title' => t('Note'),
    '#description' => t('You can enter a note that will accompany the refund. Notes will be sent in an email to the customer.'),
    '#default_value' => '',
    '#size' => 60,
    '#maxlength' => 255,
  );

  $form = confirm_form($form,
    t('What amount do you want to refund?'),
    'admin/commerce/orders/' . $order->order_id . '/payment',
    '',
    t('Refund'),
    t('Cancel'),
    'confirm'
  );

  return $form;
}

/**
 * Validate handler: check the credit amount before attempting a refund request.
 */
function commerce_amazon_lpa_refund_form_validate($form, &$form_state) {
  $transaction = $form_state['transaction'];
  $order = $form_state['order'];
  $amount = $form_state['values']['amount'];
  $balance = commerce_payment_order_balance($order);

  // Ensure a positive numeric amount has been entered for refund.
  if (!is_numeric($amount) || $amount <= 0) {
    form_set_error('amount', t('You must specify a positive numeric amount to refund.'));
  }

  // Since Amazon supports up to 10 refunds per capture, we need to ensure that
  // the refund doesn't exceed their refund restrictions. For an individual
  // capture, we can only refund up to 15% or $75 (which ever is smaller) of the
  // original captured amount.
  $transaction_amount = commerce_currency_amount_to_decimal($transaction->amount, $transaction->currency_code);
  $order_balance = commerce_currency_amount_to_decimal($balance['amount'], $transaction->currency_code);
  $max_refund = ($transaction->amount > 50000) ? $transaction_amount + 7500 :
    $transaction->amount * 1.15;

  // Ensure the amount is less than or equal to the remaining captured amount.
  // We take the maximum possible refund, subtract the balance which would
  // include any additional refunds that have already been processed.
  if (($max_refund - $order_balance) < commerce_currency_decimal_to_amount($amount, $transaction->currency_code)) {
    form_set_error('amount', t('The maximum refund allowed is @max_refund.', array('@max_refund' => commerce_currency_format($max_refund, $transaction->currency_code))));
  }

  // If the transaction is older than 60 days, display an error message and
  // redirect.
  if (time() - $transaction->created > 86400 * 60) {
    drupal_set_message(t('This transaction has passed its 60 day limit for issuing refunds.'), 'error');
    drupal_goto('admin/commerce/orders/' . $form_state['order']->order_id . '/payment');
  }
}

/**
 * Submit handler: process a refund.
 */
function commerce_amazon_lpa_refund_form_submit($form, &$form_state) {
  $transaction = $form_state['transaction'];
  $amount = $form_state['values']['amount'];
  $note = $form_state['values']['note'];
  $order = $form_state['order'];
  $payment_method = $form_state['payment_method'];

  // Set up the refund.
  $api = AmazonLPA::instance();
  $data = $api->refund(entity_metadata_wrapper('commerce_order', $order), $transaction->remote_id, $amount, $note);

  $credit_transaction = commerce_payment_transaction_new($payment_method['method_id'], $order->order_id);
  $credit_transaction->instance_id = $payment_method['instance_id'];
  $transaction->payload[REQUEST_TIME . '-refund'] = $data;

  $api->processRefundTransaction($credit_transaction, $data);

  if ($transaction->status == COMMERCE_PAYMENT_STATUS_SUCCESS) {
    drupal_set_message(t('Refund for @amount completed successfully.', array(
      '@amount' => commerce_currency_format($amount, $transaction->currency_code, NULL, FALSE),
    )));
  }
  elseif ($transaction->status == COMMERCE_PAYMENT_STATUS_PENDING) {
    drupal_set_message(t('Refund for @amount is in progress.', array(
      '@amount' => commerce_currency_format($amount, $transaction->currency_code, NULL, FALSE),
    )));
  }
  else {
    // Display a failure message and response reason from Amazon.
    drupal_set_message(t('Refund failed: @reason', array('@reason' => '<pre>' . print_r($data, TRUE) . '</pre>'), 'error'));
  }

  $form_state['redirect'] = 'admin/commerce/orders/' . $order->order_id . '/payment';
}

/**
 * Form callback: allows the user to issue a credit on a prior transaction.
 */
function commerce_amazon_lpa_close_form($form, &$form_state, $order, $transaction) {
  $form_state['order'] = $order;
  $form_state['transaction'] = $transaction;

  // Load and store the payment method instance for this transaction.
  $payment_method = commerce_payment_method_instance_load($transaction->instance_id);
  $form_state['payment_method'] = $payment_method;

  $default_amount = number_format(commerce_currency_amount_to_decimal($transaction->amount, $transaction->currency_code), 2, '.', '');

  $form['amount'] = array(
    '#type' => 'textfield',
    '#title' => t('Authorized amount'),
    '#description' => t('The authorization for this amount will be released. You will not be able to process any further transactions with this authorization once it is closed.'),
    '#default_value' => $default_amount,
    '#field_suffix' => check_plain($transaction->currency_code),
    '#disabled' => TRUE,
    '#size' => 16,
  );

  $form['reason'] = array(
    '#type' => 'textfield',
    '#title' => t('Reason'),
    '#description' => t('You can enter a reason for closing this transaction. Reasons will be sent in an email to the customer.'),
    '#default_value' => '',
    '#size' => 60,
    '#maxlength' => 255,
  );

  $form = confirm_form($form,
    t('Are you sure you want to close this authorization?'),
    'admin/commerce/orders/' . $order->order_id . '/payment',
    '',
    t('Close authorization'),
    t('Cancel'),
    'confirm'
  );

  return $form;
}

/**
 * Validate handler: check the credit amount before attempting a refund request.
 */
function commerce_amazon_lpa_close_form_validate($form, &$form_state) {
}

/**
 * Submit handler: process a refund.
 */
function commerce_amazon_lpa_close_form_submit($form, &$form_state) {
  $transaction = $form_state['transaction'];
  $order = $form_state['order'];
  $reason = $form_state['values']['reason'];

  $api = AmazonLPA::instance();
  $response = $api->closeAuthorization(entity_metadata_wrapper('commerce_order', $order), $transaction->remote_id, $reason);

  // Error handling.
  if ($response) {
    drupal_set_message(t('Authorization close request sent.'));
  }
  else {
    // Display a failure message and response reason from Amazon.
    drupal_set_message(t('Error: @reason', array('@reason' => '<pre>' . print_r($response, TRUE) . '</pre>'), 'error'));
  }

  // Add this response to the payment transaction payload.
  $transaction->payload[REQUEST_TIME . '-close'] = $response;
  commerce_payment_transaction_save($transaction);

  $form_state['redirect'] = 'admin/commerce/orders/' . $order->order_id . '/payment';
}
