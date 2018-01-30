<?php

/**
 * Alter a request's parameters sent to the API.
 *
 * @param array $params
 *   Parameters sent to the API.
 * @param string $type
 *   The API call type.
 * @param mixed $data
 *   Extra data, such as the order.
 */
function hook_commerce_amazon_lpa_request_params_alter(array &$params, $type, $data) {
  switch ($type) {
    case 'refund':
      $params['seller_refund_note'] = t('Refund note');
      break;
  }
}

/**
 * Event when a non-sync authorization comes back with a soft decline.
 *
 * @param object $order
 *   The order with a declined transaction.
 * @param object $transaction
 *   The transaction that was declined.
 * @param array $data
 *   Arbitrary data from API response.
 */
function hook_commerce_amazon_lpa_auth_soft_decline($order, $transaction, array $data) {
  // Send an email.
}

/**
 * Event when a non-sync authorization comes back with a hard decline.
 *
 * @param object $order
 *   The order with a declined transaction.
 * @param object $transaction
 *   The transaction that was declined.
 * @param array $data
 *   Arbitrary data from API response.
 */
function hook_commerce_amazon_lpa_auth_hard_decline($order, $transaction, array $data) {
  // Send an email.
}
