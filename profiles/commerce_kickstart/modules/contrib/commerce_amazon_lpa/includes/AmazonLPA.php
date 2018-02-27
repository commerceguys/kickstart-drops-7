<?php

/**
 * @file
 * AmazonLPA class for integrating with the Login and Pay Amazon PHP SDK.
 */

/**
 * API Wrapper class.
 */
class AmazonLPA {

  // Order field name.
  const REFERENCE_ID_FIELD = 'commerce_amazon_lpa_order_id';

  // ENTER_COLOR_PARAMETER.
  const BUTTON_COLOR_GOLD = 'Gold';
  const BUTTON_COLOR_LIGHT_GRAY = 'LightGray';
  const BUTTON_COLOR_DARK_GRAY = 'DarkGray';

  // ENTER_SIZE_PARAMETER.
  const BUTTON_SIZE_SMALL = 'small';
  const BUTTON_SIZE_MEDIUM = 'medium';
  const BUTTON_SIZE_LARGE = 'large';
  const BUTTON_SIZE_X_LARGE = 'x-large';

  // Operation modes.
  const OPERATION_MODE_LOGIN_AND_PAY = 'login_pay';
  const OPERATION_MODE_PAY_ONLY = 'pay_only';

  // Checkout strategies.
  const STRATEGY_AMAZON = 'amazon';
  const STRATEGY_NORMAL = 'normal';

  // Payment modes.
  const CAPTURE_AUTH_CAPTURE = 'authorize_capture';
  const CAPTURE_SHIPMENT_CAPTURE = 'shipment_capture';
  const CAPTURE_MANUAL_CAPTURE = 'manual_capture';

  const AUTH_SYNC = 'automatic_sync';
  const AUTH_NONSYNC = 'automatic_nonsync';
  const AUTH_MANUAL = 'manual_nonsync';

  const ENV_SANDBOX = 'sandbox';
  const ENV_LIVE = 'live';

  /**
   * The current Amazon user info for instance.
   *
   * @var array
   */
  protected $user_info = array();

  /**
   * The integration platform IDs.
   *
   * @var array
   */
  protected $platform_ids = array(
    'UK' => 'A33DP3YE7OHVLV',
    'DE' => 'A1ZBM19RFMXA83',
    'US' => 'A294FY3QW7KJ8X',
  );

  /**
   * Returns if the API is working in sandbox mode.
   *
   * @return bool
   *   Returns TRUE if sandbox, or FALSE for production.
   */
  public static function is_sandbox() {
    return (variable_get('commerce_amazon_lpa_environment', self::ENV_SANDBOX) == self::ENV_SANDBOX);
  }

  /**
   * Returns if running in ERP mode.
   *
   * @return bool
   *   Returns TRUE if in ERP mode, or FALSE for not.
   */
  public static function is_erp_mode() {
    return (bool) variable_get('commerce_amazon_lpa_erp_mode', 0);
  }

  /**
   * Checks if Login and Pay with Amazon has been configured.
   *
   * @return bool
   *   Returns TRUE if service has been configured.
   */
  public static function is_configured() {
    return (
    variable_get('commerce_amazon_lpa_merchant_id') !== NULL ||
    variable_get('commerce_amazon_lpa_access_key') !== NULL ||
    variable_get('commerce_amazon_lpa_secret_key') !== NULL
    );
  }

  /**
   * Returns if the Login and Pay with Amazon buttons have been hidden.
   *
   * @return bool
   *   Returns boolean indicating hidden status.
   */
  public static function is_hidden() {
    $hidden_mode = variable_get('commerce_amazon_lpa_hidden_mode');
    return (
      $GLOBALS['user']->uid != 1 &&
      !empty($hidden_mode) &&
      !user_has_role(variable_get('commerce_amazon_lpa_hidden_mode'))
    );
  }

  /**
   * Returns the current operation mode.
   *
   * @return string
   *   The current operation mode.
   */
  public static function get_operation_mode() {
    return variable_get('commerce_amazon_lpa_operation_mode', AmazonLPA::OPERATION_MODE_LOGIN_AND_PAY);
  }

  /**
   * Returns currency for a seller's region, or region currency map.
   *
   * @param null|string $region
   *    A region code.
   *
   * @return array|string|bool
   *    Array or single region currency code.
   */
  public static function get_region_currency_code($region = NULL) {
    $currencies = array(
      'DE' => 'EUR',
      'UK' => 'GBP',
      'US' => 'USD',
    );

    if ($region === NULL) {
      return $currencies;
    }
    elseif (isset($currencies[$region])) {
      return $currencies[$region];
    }
    else {
      return FALSE;
    }
  }

  /**
   * Returns the language code for a region.
   *
   * @param null $region
   *
   * @return array|bool
   */
  public static function get_region_langcode($region = NULL) {
    $lang_codes = array(
      'DE' => 'de-DE',
      'UK' => 'en-GB',
      'US' => 'en-US',
    );

    if ($region === NULL) {
      return $lang_codes;
    }
    elseif (isset($lang_codes[$region])) {
      return $lang_codes[$region];
    }
    else {
      return FALSE;
    }
  }

  /**
   * Returns the current capture mode.
   *
   * @return string
   *    The current capture mode.
   */
  public static function get_capture_mode() {
    return variable_get('commerce_amazon_lpa_capture_mode', 'shipment_capture');
  }

  /**
   * Get the current authorization mode.
   *
   * @return string
   *    The current authorization mode.
   */
  public static function get_authorization_mode() {
    return variable_get('commerce_amazon_lpa_authorization_mode', self::AUTH_NONSYNC);
  }

  /**
   * Helper function to get a new instance.
   *
   * @return \AmazonLPA
   *    The API instance.
   */
  public static function instance() {
    return new self();
  }

  /**
   * @var \AmazonPayClient
   */
  protected $client;

  /**
   * AmazonLPA constructor.
   */
  public function __construct() {
    if (!self::is_configured()) {
      throw new Exception(t('You must configured Login and Pay with Amazon.'));
    }
    $this->client = new AmazonPayClient(array(
      'merchant_id' => variable_get('commerce_amazon_lpa_merchant_id'),
      'access_key' => variable_get('commerce_amazon_lpa_access_key'),
      'secret_key' => variable_get('commerce_amazon_lpa_secret_key'),
      'client_id' => variable_get('commerce_amazon_lpa_client_id'),
      'region' => variable_get('commerce_amazon_lpa_region'),
      'sandbox' => self::is_sandbox(),
    ));
  }

  /**
   * Returns current API client.
   *
   * @return \AmazonPayClient
   *    Returns SDK client.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Returns the current Amazon user info.
   *
   * @return array|mixed
   *    The current Amazon user information.
   */
  public function getUserInfo() {
    if (empty($this->user_info)) {
      if (isset($_COOKIE['amazon_Login_accessToken'])) {
        $access_token = $_COOKIE['amazon_Login_accessToken'];
        try {
          $this->user_info = $this->client->getUserInfo($access_token);
        }
        catch (Exception $e) {

        }
      }
    }
    return $this->user_info;
  }

  /**
   * Sends API request to cancel an order.
   *
   * @param \EntityDrupalWrapper $order
   *
   * @return bool|mixed
   *
   * @throws \Exception
   */
  public function cancel(EntityDrupalWrapper $order) {
    $contract_id = $this->getOrderReferenceId($order);
    $balance = commerce_payment_order_balance($order->value());

    if (!empty($contract_id) && $balance['amount'] > 0) {
      $params = array(
        'amazon_order_reference_id' => $contract_id,
      );

      // Allow modules to modify the request params.
      $this->alterRequestParams('cancel_order_reference', $params, $order);

      $response = $this->client->cancelOrderReference($params);
      $data = $response->toArray();

      commerce_amazon_lpa_add_debug_log(t('Debugging cancel response: !debug>'), array(
        '!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>',
      ));

      if ($this->client->success) {
        return $data;
      }
      else {
        throw new AmazonApiException(
          $data['Error']['Code'],
          $data,
          t('Unable to cancel @order_id: @reason', array(
            '@order_id' => $order->getIdentifier(),
            '@reason' => t('@code - @message', array(
              '@code' => $data['Error']['Code'],
              '@message' => $data['Error']['Message'],
            )),
          )
        ));
      }
    }

    return FALSE;
  }

  /**
   * Captures payment on an authorization.
   *
   * @param \EntityDrupalWrapper $order
   * @param string $authorization_id
   * @param array|null $balance
   *    A price array structure for the balance to process.
   *
   * @return bool|mixed
   *
   * @throws \Exception
   */
  public function capture(EntityDrupalWrapper $order, $authorization_id, $balance = NULL) {
    $contract_id = $this->getOrderReferenceId($order);

    if (!$balance) {
      $balance = commerce_payment_order_balance($order->value());
    }

    if (!empty($contract_id) && $balance['amount'] > 0) {
      $params = array(
        'amazon_order_reference_id' => $contract_id,
        'amazon_authorization_id' => $authorization_id,
        'capture_amount' => commerce_currency_amount_to_decimal($balance['amount'], $balance['currency_code']),
        'currency_code' => $balance['currency_code'],
        'capture_reference_id' => 'capture_' . $order->order_id->value() . '_' . REQUEST_TIME,
        'transaction_timeout' => 0,
      );

      // Allow modules to modify the request params.
      $this->alterRequestParams('capture', $params, $order);

      $response = $this->client->capture($params);
      $data = $response->toArray();

      commerce_amazon_lpa_add_debug_log(t('Debugging capture response: !debug>'), array('!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>'));

      if ($this->client->success) {
        return $data['CaptureResult']['CaptureDetails'];
      }
      else {
        throw new AmazonApiException(
          $data['Error']['Code'],
          $data,
          t('Unable to capture payment for @order_id: @reason', array(
            '@order_id' => $order->getIdentifier(),
            '@reason' => t('@code - @message', array(
              '@code' => $data['Error']['Code'],
              '@message' => $data['Error']['Message'],
            )),
          )));
      }
    }

    return FALSE;
  }

  /**
   * Closes an authorization with Amazon.
   *
   * @param \EntityDrupalWrapper $order
   * @param $authorization_id
   * @param string $reason
   *
   * @return bool|mixed
   *
   * @throws \Exception
   */
  public function closeAuthorization(EntityDrupalWrapper $order, $authorization_id, $reason = '') {

    $params = array(
      'amazon_authorization_id' => $authorization_id,
      'closure_reason' => $reason,
    );

    // Allow modules to modify the request params.
    $this->alterRequestParams('close_authorization', $params, $order);

    $response = $this->client->closeAuthorization($params);
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging close authorization response: !debug>'), array(
      '!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>',
    ));

    if ($this->client->success) {
      return $data;
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to close authorization for @order_id: @reason', array(
          '@order_id' => $order->getIdentifier(),
          '@reason' => t('@code - @message', array(
            '@code' => $data['Error']['Code'],
            '@message' => $data['Error']['Message'],
          )),
        )));
    }
  }

  /**
   * Creates an authorization transaction on an order.
   *
   * @param \EntityDrupalWrapper $order
   *   Entity metadata wrapper for a commerce order entity.
   * @param bool $capture_now
   * @param array|null $balance
   *    A price array structure for the balance to process.
   *
   * @return mixed
   *   Authorization transaction result.
   *
   * @throws \Exception
   */
  public function authorize(EntityDrupalWrapper $order, $capture_now = FALSE, $balance = NULL) {

    if (!$balance) {
      $balance = commerce_payment_order_balance($order->value());
    }

    $params = array(
      'amazon_order_reference_id' => $this->getOrderReferenceId($order),
      'authorization_amount' => commerce_currency_amount_to_decimal($balance['amount'], $balance['currency_code']),
      'currency_code' => $balance['currency_code'],
      'authorization_reference_id' => 'auth_' . $order->order_id->value() . '_' . REQUEST_TIME,
      'capture_now' => $capture_now,
    );

    if ($capture_now) {
      $params['seller_authorization_note'] = check_plain(variable_get('commerce_amazon_lpa_capture_auth_statement', ''));
    }

    // If using sync, set transaction timeout to 0.
    if (self::get_authorization_mode() == self::AUTH_SYNC) {
      $params['transaction_timeout'] = 0;
    }
    // Otherwise use max timeout.
    else {
      $params['transaction_timeout'] = variable_get('commerce_amazon_lpa_transaction_timeout', 1440);
    }

    // Allow modules to modify the request params.
    $this->alterRequestParams('authorize', $params, $order);

    $response = $this->client->authorize($params);
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging authorize response: !debug'), array('!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>'));

    if ($this->client->success) {
      return $data['AuthorizeResult']['AuthorizationDetails'];
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to authorize payment for @order_id: @reason', array(
          '@order_id' => $order->getIdentifier(),
          '@reason' => t('@code - @message', array(
            '@code' => $data['Error']['Code'],
            '@message' => $data['Error']['Message'],
          )),
        )));
    }
  }

  /**
   * Processes and saves a payment transaction that is in authorization.
   *
   * @param object $transaction
   *   The transaction in authorization.
   * @param array $data
   *   API data.
   *
   * @throws \Exception
   *   Exception.
   */
  public function processAuthorizeTransaction($transaction, array $data) {
    $order = commerce_order_load($transaction->order_id);

    // Amazon has at least two different ways that Buyer information is conveyed
    // back to an API client. The first is that the OrderReference has a Buyer
    // object that contains a name and email address (with an optional phone no.).
    // The second is an AuthorizationBillingDetails object that is returned on
    // some versions of the API. At the time of writing, the US version of the API
    // doesn't provide this information, but the UK version does. Since we do not
    // know what the API is going to return, we'll look for the
    // AuthorizationBillingDetails object and, if it exists, process it. If not,
    // then the Buyer information on the Order Reference will have to suffice.
    // @see https://payments.amazon.com/documentation/apireference/201752660#201752450
    // @see https://payments.amazon.co.uk/developer/documentation/apireference/201752450
    $billing_address = NULL;
    if (isset($data['AuthorizationBillingAddress'])) {
      $billing_address = $data['AuthorizationBillingAddress'];
    }

    // If we found a billing address, sync it.
    if ($billing_address) {
      try {
        commerce_amazon_lpa_amazon_address_to_customer_profile($order, 'billing', $billing_address);
        commerce_order_save($order);
      }
      catch (Exception $e) {
        watchdog('commerce_amazon_lpa', 'Error processing order billing information for Amazon: !error', array('!error' => '<pre>' . print_r($data, TRUE) . '</pre>'), WATCHDOG_ERROR);
      }
    }
    // Otherwise just use shipping address so it isn't empty.
    else {
      $order_reference = $this->getOrderRef(entity_metadata_wrapper('commerce_order', $order));
      if (isset($order_reference['Destination']['PhysicalDestination'])) {
        $shipping_address = $order_reference['Destination']['PhysicalDestination'];
        commerce_amazon_lpa_amazon_address_to_customer_profile($order, 'billing', $shipping_address);
        commerce_amazon_lpa_amazon_address_to_customer_profile($order, 'shipping', $shipping_address);
        commerce_order_save($order);
      }
    }

    $transaction->remote_id = $data['AmazonAuthorizationId'];
    $transaction->amount = commerce_currency_decimal_to_amount($data['AuthorizationAmount']['Amount'], $data['AuthorizationAmount']['CurrencyCode']);
    $transaction->currency_code = $data['AuthorizationAmount']['CurrencyCode'];

    $transaction->data['commerce_amazon_lpa']['environment'] = variable_get('commerce_amazon_lpa_environment', self::ENV_SANDBOX);
    $transaction->data['commerce_amazon_lpa']['auth_reference_id'] = $data['AuthorizationReferenceId'];
    $transaction->data['commerce_amazon_lpa']['transaction_type'] = 'authorization';

    // Capture is only pending if pre-authorized. Otherwise declined during
    // validation. Check the payment object's state and update transaction
    // status.
    commerce_amazon_lpa_payment_state_to_status($transaction, $data['AuthorizationStatus']);

    commerce_amazon_lpa_transaction_message_update_data($transaction, 'Authorization', $data['AuthorizationStatus']);
    $transaction->payload[REQUEST_TIME . '-authorization'] = $data;

    // If we did capture, set it up so that we can properly refund, etc.
    if ($transaction->status == COMMERCE_PAYMENT_STATUS_SUCCESS && $data['AuthorizationStatus']['ReasonCode'] == 'MaxCapturesProcessed') {
      // Create a capture transaction.
      if (isset($data['IdList']['member'])) {
        $capture_id = $data['IdList']['member'];
      }
      else {
        $capture_id = $data['IdList']['Id'];
      }
      $capture_details = $this->getCaptureDetails($capture_id);
      $transaction->remote_id = $capture_details['AmazonCaptureId'];
      $transaction->data['commerce_amazon_lpa']['capture_id'] = $capture_details['AmazonCaptureId'];
      $transaction->data['commerce_amazon_lpa']['transaction_type'] = 'capture';
      // The authorization will be "Closed" but the capture will be "Completed".
      // The value returned by Amazon must be overridden so that refunds will
      // work.
      commerce_amazon_lpa_payment_state_to_status($transaction, $capture_details['CaptureStatus']);
      commerce_amazon_lpa_transaction_message_update_data($transaction, 'Capture', $capture_details['CaptureStatus']);
      $transaction->payload[REQUEST_TIME . '-capture'] = $capture_details;

      // Create a revision, since we have changed the remote ID.
      $transaction->revision = TRUE;
      $transaction->log = t('Authorization was captured');
    }
    // If we have a pending transaction, we need to make sure the order status
    // matches the one configured.
    if ($transaction->status == COMMERCE_PAYMENT_STATUS_PENDING &&
        $data['AuthorizationStatus']['State'] == 'Open' &&
        variable_get('commerce_amazon_lpa_auth_order_status', 'pending') != $order->status) {
      $order->data['commerce_amazon_lpa_set_as_auth'] = TRUE;
    }

    commerce_payment_transaction_save($transaction);

    // If the authorization is Rejected, then set the order lock on the
    // order's data object to forbid any further transactions from taking
    // place.
    if (AmazonLPA::get_authorization_mode() == AmazonLPA::AUTH_NONSYNC) {
      if ($data['AuthorizationStatus']['State'] == 'Declined') {
        $reason_code = $data['AuthorizationStatus']['ReasonCode'];
        if ($reason_code == 'InvalidPaymentMethod') {
          rules_invoke_all('commerce_amazon_lpa_nonsync_auth_soft_decline', $order, $transaction, $data);
        }
        else {
          $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
          $order_wrapper->{AmazonLPA::REFERENCE_ID_FIELD} = '';
          commerce_amazon_lpa_user_logout($order_wrapper->owner->value());
          commerce_order_status_update($order, 'cart', FALSE, TRUE, t('The payment method was rejected by Amazon.'));

          rules_invoke_all('commerce_amazon_lpa_nonsync_auth_hard_decline', $order, $transaction, $data);
        }
      }
    }
  }

  /**
   * Processes and saves a payment transaction that is a capture.
   *
   * @param object $transaction
   *   The transaction in authorization.
   * @param array $data
   *   API data.
   *
   * @throws \Exception
   *   Exception.
   */
  public function processCaptureTransaction($transaction, array $data) {
    $transaction->remote_id = $data['AmazonCaptureId'];
    $transaction->amount = commerce_currency_decimal_to_amount($data['CaptureAmount']['Amount'], $data['CaptureAmount']['CurrencyCode']);
    $transaction->currency_code = $data['CaptureAmount']['CurrencyCode'];

    $transaction->data['commerce_amazon_lpa']['environment'] = variable_get('commerce_amazon_lpa_environment', self::ENV_SANDBOX);
    $transaction->data['commerce_amazon_lpa']['auth_reference_id'] = $data['AmazonCaptureId'];
    $transaction->data['commerce_amazon_lpa']['transaction_type'] = 'capture';

    commerce_amazon_lpa_payment_state_to_status($transaction, $data['CaptureStatus']);
    commerce_amazon_lpa_transaction_message_update_data($transaction, 'Capture', $data['CaptureStatus']);
    $transaction->payload[REQUEST_TIME . '-capture'] = $data;

    commerce_payment_transaction_save($transaction);
  }

  /**
   * Processes and saves a payment transaction that is a refund.
   *
   * @param object $transaction
   *   The transaction in authorization.
   * @param array $data
   *   API data.
   *
   * @throws \Exception
   *   Exception.
   */
  public function processRefundTransaction($transaction, array $data) {
    $transaction->remote_id = $data['AmazonRefundId'];
    $transaction->amount = commerce_currency_decimal_to_amount($data['RefundAmount']['Amount'], $data['RefundAmount']['CurrencyCode']) * -1;
    $transaction->currency_code = $data['RefundAmount']['CurrencyCode'];

    $transaction->data['commerce_amazon_lpa']['environment'] = variable_get('commerce_amazon_lpa_environment', self::ENV_SANDBOX);
    $transaction->data['commerce_amazon_lpa']['refund_id'] = $data['AmazonRefundId'];
    $transaction->data['commerce_amazon_lpa']['transaction_type'] = 'refund';

    commerce_amazon_lpa_payment_state_to_status($transaction, $data['RefundStatus']);
    commerce_amazon_lpa_transaction_message_update_data($transaction, 'Refund', $data['RefundStatus']);
    $transaction->payload[REQUEST_TIME . '-refund'] = $data;
    commerce_payment_transaction_save($transaction);
  }

  /**
   * Confirms that an order reference has been fulfilled to Amazon Payments.
   *
   * Call the CloseOrderReference operation to indicate that a previously
   * confirmed order reference has been fulfilled (fully or partially) and that
   * you do not expect to create any new authorizations on this order reference.
   *
   * You can still capture funds against open authorizations on the order
   * reference.
   *
   * @link https://payments.amazon.com/documentation/apireference/201752000
   *
   * @param \EntityDrupalWrapper $order
   *   Entity metadata wrapper for a commerce order entity.
   *
   * @return mixed
   *   Response details.
   *
   * @throws \Exception
   */
  public function closeOrderRef(EntityDrupalWrapper $order) {
    $params = array(
      'amazon_order_reference_id' => $this->getOrderReferenceId($order),
    );

    // Allow modules to modify the request params.
    $this->alterRequestParams('close_order_reference', $params, $order);

    $response = $this->client->closeOrderReference($params);
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging close order reference response: !debug>'), array('!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>'));

    if ($this->client->success) {
      return $data;
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to close the reference for @order_id: @reason', array(
          '@order_id' => $order->getIdentifier(),
          '@reason' => t('@code - @message', array(
            '@code' => $data['Error']['Code'],
            '@message' => $data['Error']['Message'],
          )),
        )));
    }
  }

  /**
   * Gets the order reference from Amazon Payments.
   *
   * The GetOrderReferenceDetails operation returns details about the Order
   * Reference object and its current state.
   *
   * @link https://payments.amazon.com/documentation/apireference/201751970
   *
   * @param \EntityDrupalWrapper $order
   *   Entity metadata wrapper for a commerce order entity.
   *
   * @return array
   *   Array of order reference detail information.
   *
   * @throws \Exception
   */
  public function getOrderRef(EntityDrupalWrapper $order) {
    $params = array(
      'amazon_order_reference_id' => $this->getOrderReferenceId($order),
    );

    // Allow modules to modify the request params.
    $this->alterRequestParams('get_order_reference', $params, $order);

    $response = $this->client->getOrderReferenceDetails($params);
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging get order reference response: !debug>'), array('!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>'));

    if ($this->client->success) {
      return $data['GetOrderReferenceDetailsResult']['OrderReferenceDetails'];
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to get the order reference for @order_id: @reason', array(
          '@order_id' => $order->getIdentifier(),
          '@reason' => t('@code - @message', array(
            '@code' => $data['Error']['Code'],
            '@message' => $data['Error']['Message'],
          )),
        )));
    }
  }

  /**
   * Confirms an order reference.
   *
   * Call the ConfirmOrderReference operation after the order reference is free
   * of constraints and all required information has been set on the order
   * reference. After you call this operation, the order reference is set to
   * the Open state and you can submit authorizations against the order
   * reference.
   *
   * @link https://payments.amazon.com/documentation/apireference/201751980
   *
   * @param \EntityDrupalWrapper $order
   *   Entity metadata wrapper for a commerce order entity.
   *
   * @return mixed
   *   Array of order reference detail information.
   *
   * @throws \Exception
   */
  public function confirmOrderRef(EntityDrupalWrapper $order) {
    $params = array(
      'amazon_order_reference_id' => $this->getOrderReferenceId($order),
    );

    // Allow modules to modify the request params.
    $this->alterRequestParams('confirm_order_reference', $params, $order);

    $response = $this->client->confirmOrderReference($params);
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging confirm order reference response: !debug>'), array('!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>'));

    if ($this->client->success) {
      return $data;
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to confirm the order reference for @order_id: @reason', array(
          '@order_id' => $order->getIdentifier(),
          '@reason' => t('@code - @message', array(
            '@code' => $data['Error']['Code'],
            '@message' => $data['Error']['Message'],
          )),
        )));
    }
  }

  /**
   * Sets an order reference to Amazon Payments.
   *
   * Call the SetOrderReferenceDetails operation to specify order details such
   * as the amount of the order, a description of the order, and other order
   * attributes.
   *
   * @link https://payments.amazon.com/documentation/apireference/201751960
   *
   * @param \EntityDrupalWrapper $order
   *   Entity metadata wrapper for a commerce order entity.
   *
   * @return mixed
   *   The order reference details response.
   *
   * @throws \Exception
   */
  public function setOrderRef(EntityDrupalWrapper $order) {
    $params = array(
      'amazon_order_reference_id' => $this->getOrderReferenceId($order),
      'amount' => $order->commerce_order_total->amount_decimal->value(),
      'currency_code' => $order->commerce_order_total->currency_code->value(),
      'seller_order_id' => $order->getIdentifier(),
      'store_name' => variable_get('site_name'),
      'platform_id' => $this->platform_ids[variable_get('commerce_amazon_lpa_region')],
    );

    // Allow modules to modify the request params.
    $this->alterRequestParams('set_order_reference', $params, $order);

    $response = $this->client->setOrderReferenceDetails($params);
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging set order reference response: !debug>', array(
      '!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>',
    )));

    if ($this->client->success) {
      return $data['SetOrderReferenceDetailsResult']['OrderReferenceDetails'];
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to set the order reference for @order_id: @reason', array(
          '@order_id' => $order->getIdentifier(),
          '@reason' => t('@code - @message', array(
            '@code' => $data['Error']['Code'],
            '@message' => $data['Error']['Message'],
          )),
        )));
    }
  }

  /**
   * @param $capture_id
   *
   * @return mixed
   * @throws \Exception
   */
  public function getCaptureDetails($capture_id) {
    $params = array(
      'amazon_capture_id' => $capture_id,
    );

    // Allow modules to modify the request params.
    $this->alterRequestParams('get_capture_details', $params);

    $response = $this->client->getCaptureDetails($params);
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging capture details response: !debug>', array('!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>')));

    if ($this->client->success) {
      return $data['GetCaptureDetailsResult']['CaptureDetails'];
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to capture payment for @order_id: @reason', array(
          '@order_id' => $capture_id,
          '@reason' => t('@code - @message', array(
            '@code' => $data['Error']['Code'],
            '@message' => $data['Error']['Message'],
          )),
        )));
    }
  }

  /**
   * @param string $authorization_id
   *
   * @return mixed
   * @throws \Exception
   */
  public function getAuthorizationDetails($authorization_id) {
    $response = $this->client->getAuthorizationDetails(array(
      'amazon_authorization_id' => $authorization_id,
    ));
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging authorization details response: !debug>', array('!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>')));

    if ($this->client->success) {
      return $data['GetAuthorizationDetailsResult']['AuthorizationDetails'];
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to get authorization details for @order_id', array('@order_id' => $authorization_id)
      ));
    }
  }

  /**
   * Refunds a transation for specified amount.
   *
   * @param $order
   * @param $captureId
   * @param $amount
   * @param string $note
   *
   * @return mixed
   *
   * @throws \Exception
   */
  public function refund(EntityDrupalWrapper $order, $captureId, $amount, $note = '') {
    $balance = commerce_payment_order_balance($order->value());

    $params = array(
      'amazon_capture_id' => $captureId,
      'refund_amount' => $amount,
      'currency_code' => $balance['currency_code'],
      'refund_reference_id' => 'refund_' . $order->order_id->value() . '_' . REQUEST_TIME,
      'seller_refund_note' => $note,
    );

    // Allow modules to modify the request params.
    $this->alterRequestParams('refund', $params);

    $response = $this->client->refund($params);
    $data = $response->toArray();

    commerce_amazon_lpa_add_debug_log(t('Debugging refund response: !debug>', array('!debug' => '<pre>' . check_plain(print_r($data, TRUE)) . '</pre>')));

    if ($this->client->success) {
      return $data['RefundResult']['RefundDetails'];
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
          t('Unable to refund payment for @order_id: @reason', array(
            '@order_id' => $order->getIdentifier(),
            '@reason' => t('@code - @message', array(
              '@code' => $data['Error']['Code'],
              '@message' => $data['Error']['Message'],
            )),
          )));
    }
  }

  /**
   * @param string $refund_id
   *
   * @return mixed
   * @throws \Exception
   */
  public function getRefundDetails($refund_id) {
    $response = $this->client->getRefundDetails(array(
      'amazon_refund_id' => $refund_id,
    ));
    $data = $response->toArray();

    if ($this->client->success) {
      return $data['GetRefundDetailsResult']['RefundDetails'];
    }
    else {
      throw new AmazonApiException(
        $data['Error']['Code'],
        $data,
        t('Unable to get refund details for @order_id', array('@order_id' => $refund_id)));
    }
  }

  /**
   * Returns the reference ID from an order.
   *
   * @param \EntityDrupalWrapper $order
   *   Entity metadata wrapper for a commerce order entity.
   *
   * @return mixed
   */
  public function getOrderReferenceId(EntityDrupalWrapper $order) {
    if (isset($order->{AmazonLPA::REFERENCE_ID_FIELD})) {
      return $order->{AmazonLPA::REFERENCE_ID_FIELD}->value();
    }
    return NULL;
  }

  /**
   * Invokes alter to allow modules to adjust API call parameters.
   *
   * @param $type
   * @param $params
   * @param $data
   */
  public function alterRequestParams($type, array &$params, $data = NULL) {
    drupal_alter('commerce_amazon_lpa_request_params', $params, $type, $data);
  }

}
/**
 *
 */
class AmazonApiException extends Exception {
  protected $errorCode;
  protected $response;

  /**
   *
   */
  public function __construct($error_code, array $response, $message = "", $code = 0, Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
    $this->errorCode = $error_code;
    $this->response = $response;
  }

  /**
   *
   */
  public function getErrorCode() {
    return $this->errorCode;
  }

  /**
   *
   */
  public function getResponse() {
    return $this->response;
  }

}
