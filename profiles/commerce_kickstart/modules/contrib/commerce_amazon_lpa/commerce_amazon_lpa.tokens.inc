<?php

/**
 * @file
 * Builds placeholder replacement tokens for Amazon Pay related data.
 */

/**
 * Implements hook_token_info().
 */
function commerce_amazon_lpa_token_info() {
  $type = array(
    'name' => t('Amazon Pay'),
    'description' => t('Tokens related to Amazon Pay.'),
  );

  $tokens = array();
  $tokens['account-link'] = array(
    'name' => t('Amazon account link'),
    'description' => t('Provides a link to the Amazon customer for their account, based on your configured region.'),
  );

  return array(
    'types' => array('amazon-pay' => $type),
    'tokens' => array('amazon-pay' => $tokens),
  );
}

/**
 * Implements hook_tokens().
 */
function commerce_amazon_lpa_tokens($type, $tokens, array $data = array(), array $options = array()) {
  $replacements = array();
  if ($type == 'amazon-pay' || $type == 'amazon_pay') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'account-link':
          $region = variable_get('commerce_amazon_lpa_region', 'US');
          $langcode = str_replace('-', '_', variable_get('commerce_amazon_lpa_langcode', AmazonLPA::get_region_langcode($region)));
          $replacements[$original] = sprintf('https://pay.amazon.com/%s/jr/your-account/orders?language=%s', strtolower($region), $langcode);
          break;
      }
    }
  }
  return $replacements;
}
