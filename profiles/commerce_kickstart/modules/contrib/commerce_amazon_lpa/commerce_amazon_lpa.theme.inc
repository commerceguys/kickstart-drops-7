<?php

/**
 * @file
 * Theme implementation.
 */

/**
 * Theme helper function to attach library and JavaScript settings.
 *
 * @param array $render
 *   Render array.
 * @param array $settings
 *   Settings array.
 */
function _commerce_amazon_lpa_render_helper(array &$render, array $settings) {
  $render['#attached']['library'] = array(array('commerce_amazon_lpa', 'amazon_widgets'));
  $render['#attached']['js'][] = array(
    'data' => array('AmazonLPA' => $settings),
    'type' => 'setting',
  );
  $render['#access'] = AmazonLPA::is_configured() && !AmazonLPA::is_hidden();
}

/**
 * Login button theme function.
 *
 * @param array $variables
 *   Variables array.
 *
 * @return string
 *   Login button HTML string.
 */
function theme_commerce_amazon_login_button($variables) {
  $attributes = array(
    'id' => $variables['html_id'],
    'title' => t('Login with Amazon'),
    'data-url' => url('user/login/amazon', array('absolute' => TRUE, 'query' => array('amzn' => $variables['button_parameter']))),
    'data-pay-type' => isset($variables['button_options']['payType']) ? $variables['button_options']['loginType'] : 'LwA',
    'data-pay-size' => variable_get('commerce_amazon_lpa_login_button_size', AmazonLPA::BUTTON_SIZE_MEDIUM),
    'data-pay-style' => variable_get('commerce_amazon_lpa_login_button_style', AmazonLPA::BUTTON_COLOR_GOLD),
  );

  $render['#markup'] = '<div ' . drupal_attributes($attributes) . '"></div>';
  _commerce_amazon_lpa_render_helper($render, array(
    'callbacks' => array(
      array(
        'callback' => 'Drupal.AmazonLPA.LoginButton',
        'param' => $variables['html_id'],
      ),
    ),
  ));
  return render($render);
}

/**
 * Payment button theme function.
 *
 * @param array $variables
 *   Variables array.
 *
 * @return string
 *   Payment button HTML string.
 */
function theme_commerce_amazon_payment_button($variables) {
  $attributes = array(
    'id' => $variables['html_id'],
    'title' => t('Amazon Payments makes shopping on our website even easier by using the payment information in your Amazon account.'),
    'data-pay-type' => isset($variables['button_options']['payType']) ? $variables['button_options']['payType'] : 'PwA',
    'data-pay-size' => variable_get('commerce_amazon_lpa_pay_button_size', AmazonLPA::BUTTON_SIZE_MEDIUM),
    'data-checkout-url' => url('checkout/amazon', array('absolute' => TRUE)),
  );

  $render['#markup'] = '<div' . drupal_attributes($attributes) . '></div>';

  _commerce_amazon_lpa_render_helper($render, array(
    'callbacks' => array(
      array(
        'callback' => 'Drupal.AmazonLPA.PaymentButton',
        'param' => $variables['html_id'],
      ),
    ),
  ));
  return render($render);
}

/**
 * Payment button summary link theme function.
 *
 * @param array $variables
 *   Variables array.
 *
 * @return string
 *   Summary link HTML string.
 */
function theme_commerce_amazon_payment_button__summary_link($variables) {
  $attributes = array(
    'id' => $variables['html_id'],
    'title' => t('Amazon Payments makes shopping on our website even easier by using the payment information in your Amazon account.'),
    'data-pay-type' => isset($variables['button_options']['payType']) ? $variables['button_options']['payType'] : NULL,
    'data-pay-size' => 'small',
    'data-checkout-url' => url('checkout/amazon', array('absolute' => TRUE)),
  );

  $render['#markup'] = '<div' . drupal_attributes($attributes) . '></div>';

  _commerce_amazon_lpa_render_helper($render, array(
    'callbacks' => array(
      array(
        'callback' => 'Drupal.AmazonLPA.PaymentButton',
        'param' => $variables['html_id'],
      ),
    ),
  ));

  return render($render);
}

/**
 * Address book widget theme function.
 *
 * @param array $variables
 *   Variables array.
 *
 * @return string
 *   Address book widget HTML string.
 */
function theme_commerce_amazon_addressbook_widget($variables) {
  $element_id = drupal_html_id($variables['html_id']);

  $addressbook_size = explode('x', variable_get('commerce_amazon_lpa_addressbook_size')) + array('', '250');
  $addressbook_width = ($addressbook_size[0]) ? $addressbook_size[0] . 'px' : '100%';
  $attributes = array(
    'id' => $element_id,
    'style' => array(
      'max-width: 100%;',
      'width: ' . $addressbook_width . ';',
      'height: ' . $addressbook_size[1] . 'px;',
      'display: inline-block;',
    ),
  );

  $render['#markup'] = '<div' . drupal_attributes($attributes) . '></div>';

  _commerce_amazon_lpa_render_helper($render, array(
    'addressBookOptions' => array(
      'displayMode' => (!isset($variables['display_mode'])) ? 'edit' : $variables['display_mode'],
    ),
    'callbacks' => array(
      array(
        'callback' => 'Drupal.AmazonLPA.AddressBookWidget',
        'param' => $element_id,
      ),
    ),
  ));

  return render($render);
}

/**
 * Wallet widget theme function.
 *
 * @param array $variables
 *   Variables array.
 *
 * @return string
 *   Themed wallet widget HTML.
 */
function theme_commerce_amazon_wallet_widget($variables) {
  $element_id = drupal_html_id($variables['html_id']);

  $wallet_size = explode('x', variable_get('commerce_amazon_lpa_wallet_size')) + array('', '250');
  $wallet_width = ($wallet_size[0]) ? $wallet_size[0] . 'px' : '100%';
  $attributes = array(
    'id' => $element_id,
    'style' => array(
      'max-width: 100%;',
      'width: ' . $wallet_width . ';',
      'height: ' . $wallet_size[1] . 'px;',
      'display: inline-block;',
    ),
  );

  $render['#markup'] = '<div' . drupal_attributes($attributes) . '></div>';

  _commerce_amazon_lpa_render_helper($render, array(
    'walletOptions' => array(
      'displayMode' => (!isset($variables['display_mode'])) ? 'edit' : $variables['display_mode'],
    ),
    'orderReferenceId' => $variables['order_reference_id'],
    'callbacks' => array(
      array(
        'callback' => 'Drupal.AmazonLPA.WalletWidget',
        'param' => $element_id,
      ),
    ),
  ));

  return render($render);
}
