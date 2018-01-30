<?php

/**
 * @file
 */

/**
 *
 */
class AmazonPayClient {

  const MWS_VERSION = '2013-01-01';

  const MAX_ERROR_RETRY = 3;

  private $userAgent = NULL;

  private $mwsEndpointPath = NULL;

  private $mwsEndpointUrl = NULL;

  private $config = array(
    'merchant_id' => NULL,
    'secret_key' => NULL,
    'access_key' => NULL,
    'region' => NULL,
    'currency_code' => NULL,
    'sandbox' => FALSE,
    'platform_id' => NULL,
    'cabundle_file' => NULL,
    'application_name' => NULL,
    'application_version' => NULL,
    'proxy_host' => NULL,
    'proxy_port' => -1,
    'proxy_username' => NULL,
    'proxy_password' => NULL,
    'client_id' => NULL,
    'app_id' => NULL,
    'handle_throttle' => TRUE,
  );

  private $modePath = NULL;

  protected $mwsServiceUrl = NULL;

  public $mwsServiceUrls = array(
    'eu' => 'mws-eu.amazonservices.com',
    'na' => 'mws.amazonservices.com',
    'jp' => 'mws.amazonservices.jp',
  );

  public $profileEndpointUrls = array(
    'uk' => 'amazon.co.uk',
    'us' => 'amazon.com',
    'de' => 'amazon.de',
    'jp' => 'amazon.co.jp',
  );

  public $regionMappings = array(
    'de' => 'eu',
    'uk' => 'eu',
    'us' => 'na',
    'jp' => 'jp',
  );

  /**
   * Boolean variable to check if the API call was a success.
   *
   * @var bool
   */
  public $success = FALSE;

  /**
   *
   */
  public function __construct($config = array()) {
    $this->checkConfigKeys($config);
    if (empty($this->config['region'])) {
      throw new \Exception("config['region'] is a required parameter and is not set");
    }
    if (!isset($this->regionMappings[strtolower($this->config['region'])])) {
      throw new \Exception($this->config['region'] . ' is not a valid region');
    }
    $this->modePath = strtolower($this->config['sandbox']) ? 'OffAmazonPayments_Sandbox' : 'OffAmazonPayments';
    $this->mwsEndpointUrl = $this->mwsServiceUrls[$this->regionMappings[strtolower($this->config['region'])]];
    $this->mwsServiceUrl = 'https://' . $this->mwsEndpointUrl . '/' . $this->modePath . '/' . self::MWS_VERSION;
    $this->mwsEndpointPath = '/' . $this->modePath . '/' . self::MWS_VERSION;
  }

  /**
   * Logs message.
   *
   * @param $message
   */
  protected function logMessage($message) {
    commerce_amazon_lpa_add_debug_log($message, array(), WATCHDOG_INFO);
  }

  /**
   * Checks if the keys of the input configuration matches the keys in the
   * config array if they match the values are taken else throws exception
   * strict case match is not performed.
   *
   * @param $config
   *
   * @throws \Exception
   */
  protected function checkConfigKeys($config) {
    foreach ($config as $key => $value) {
      if (array_key_exists($key, $this->config)) {
        if (!is_array($value) && !is_bool($value) && $key !== 'proxy_password') {
          $this->config[$key] = trim($value);
        }
        else {
          $this->config[$key] = $value;
        }
      }
      else {
        throw new \Exception('Key ' . $key . ' is either not part of the configuration or has incorrect Key name.
                check the config array key names to match your key names of your config array', 1);
      }
    }
  }

  /**
   * Gets the value for the key if the key exists in config.
   *
   * @param $name
   *
   * @return mixed
   *
   * @throws \Exception
   */
  public function __get($name) {
    if (array_key_exists(strtolower($name), $this->config)) {
      return $this->config[strtolower($name)];
    }
    else {
      throw new \Exception('Key ' . $name . ' is either not a part of the configuration array config or the ' . $name . ' does not match the key name in the config array', 1);
    }
  }

  /**
   * GetUserInfo convenience function - Returns user's profile information from
   * Amazon using the access token returned by the Button widget.
   *
   * @see http://login.amazon.com/website Step 4
   * @param $accessToken [String]
   */
  public function getUserInfo($accessToken) {
    // Get the correct Profile Endpoint URL based off the country/region provided in the config['region'].
    $environment = strtolower($this->config['sandbox']) ? "api.sandbox" : "api";
    $region = strtolower($this->config['region']);
    $profile_endpoint = 'https://' . $environment . '.' . $this->profileEndpointUrls[$region];

    if (empty($accessToken)) {
      throw new \InvalidArgumentException('Access Token is a required parameter and is not set');
    }

    // To make sure double encoding doesn't occur decode first and encode again.
    $accessToken = urldecode($accessToken);
    $url = $profile_endpoint . '/auth/o2/tokeninfo?access_token=' . str_replace('%7E', '~', rawurlencode($accessToken));

    $httpCurlRequest = new AmazonPayHttpCurl($this->config);

    $response = $httpCurlRequest->httpGet($url);
    $data = json_decode($response);

    // Ensure that the Access Token matches either the supplied Client ID *or* the supplied App ID
    // Web apps and Mobile apps will have different Client ID's but App ID should be the same
    // As long as one of these matches, from a security perspective, we have done our due diligence.
    if (($data->aud != $this->config['client_id']) && ($data->app_id != $this->config['app_id'])) {
      // The access token does not belong to us.
      throw new \Exception('The Access Token belongs to neither your Client ID nor App ID');
    }

    // Exchange the access token for user profile.
    $url = $profile_endpoint . '/user/profile';
    $httpCurlRequest = new AmazonPayHttpCurl($this->config);

    $httpCurlRequest->setAccessToken($accessToken);
    $httpCurlRequest->setHttpHeader();
    $response = $httpCurlRequest->httpGet($url);

    $userInfo = json_decode($response, TRUE);
    return $userInfo;
  }

  /**
   * SetParametersAndPost - sets the parameters array with non empty values from the requestParameters array sent to API calls.
   * If Provider Credit Details is present, values are set by setProviderCreditDetails
   * If Provider Credit Reversal Details is present, values are set by setProviderCreditDetails.
   */
  protected function setParametersAndPost($parameters, $fieldMappings, $requestParameters) {
    /* For loop to take all the non empty parameters in the $requestParameters and add it into the $parameters array,
     * if the keys are matched from $requestParameters array with the $fieldMappings array
     */
    foreach ($requestParameters as $param => $value) {

      // Do not use trim on boolean values, or it will convert them to '0' or '1'.
      if (!is_array($value) && !is_bool($value)) {
        $value = trim($value);
      }

      // Ensure that no unexpected type coercions have happened.
      if ($param === 'capture_now' || $param === 'confirm_now' || $param === 'inherit_shipping_address') {
        if (!is_bool($value)) {
          throw new \Exception($param . ' value ' . $value . ' is of type ' . gettype($value) . ' and should be a boolean value');
        }
      }

      // When checking for non-empty values, consider any boolean as non-empty.
      if (array_key_exists($param, $fieldMappings) && (is_bool($value) || $value != '')) {

        if (is_array($value)) {
          // If the parameter is a provider_credit_details or provider_credit_reversal_details, call the respective functions to set the values.
          if ($param === 'provider_credit_details') {
            $parameters = $this->setProviderCreditDetails($parameters, $value);
          }
          elseif ($param === 'provider_credit_reversal_details') {
            $parameters = $this->setProviderCreditReversalDetails($parameters, $value);
          }

        }
        else {
          $parameters[$fieldMappings[$param]] = $value;
        }
      }
    }

    $parameters = $this->setDefaultValues($parameters, $fieldMappings, $requestParameters);
    $responseObject = $this->calculateSignatureAndPost($parameters);

    return $responseObject;
  }

  /**
   * CalculateSignatureAndPost - convert the Parameters array to string and
   * curl POST the parameters to MWS.
   */
  protected function calculateSignatureAndPost($parameters) {
    // Call the signature and Post function to perform the actions. Returns XML in array format.
    $parametersString = $this->calculateSignatureAndParametersToString($parameters);

    // POST using curl the String converted Parameters.
    $response = $this->invokePost($parametersString);

    // Send this response as args to ResponseParser class which will return the object of the class.
    $responseObject = new AmazonPayResponse($response);
    return $responseObject;
  }

  /**
   * If merchant_id is not set via the requestParameters array then it's taken
   * from the config array.
   *
   * Set the platform_id if set in the config['platform_id'] array.
   *
   * If currency_code is set in the $requestParameters and it exists in the $fieldMappings array, strtoupper it
   * else take the value from config array if set
   */
  private function setDefaultValues($parameters, $fieldMappings, $requestParameters) {
    if (empty($requestParameters['merchant_id'])) {
      $parameters['SellerId'] = $this->config['merchant_id'];
    }

    if (array_key_exists('platform_id', $fieldMappings)) {
      if (empty($requestParameters['platform_id']) && !empty($this->config['platform_id'])) {
        $parameters[$fieldMappings['platform_id']] = $this->config['platform_id'];
      }
    }

    if (array_key_exists('currency_code', $fieldMappings)) {
      if (!empty($requestParameters['currency_code'])) {
        $parameters[$fieldMappings['currency_code']] = strtoupper($requestParameters['currency_code']);
      }
      else {
        $parameters[$fieldMappings['currency_code']] = strtoupper($this->config['currency_code']);
      }
    }

    return $parameters;
  }

  /**
   * SetProviderCreditDetails - sets the provider credit details sent via the Capture or Authorize API calls.
   *
   * @param provider_id - [String]
   * @param credit_amount - [String]
   *
   * @optional currency_code - [String]
   */
  private function setProviderCreditDetails($parameters, $providerCreditInfo) {
    $providerIndex = 0;
    $providerString = 'ProviderCreditList.member.';

    $fieldMappings = array(
      'provider_id' => 'ProviderId',
      'credit_amount' => 'CreditAmount.Amount',
      'currency_code' => 'CreditAmount.CurrencyCode',
    );

    foreach ($providerCreditInfo as $key => $value) {
      $value = array_change_key_case($value, CASE_LOWER);
      $providerIndex = $providerIndex + 1;

      foreach ($value as $param => $val) {
        if (array_key_exists($param, $fieldMappings) && trim($val) != '') {
          $parameters[$providerString . $providerIndex . '.' . $fieldMappings[$param]] = $val;
        }
      }

      // If currency code is not entered take it from the config array.
      if (empty($parameters[$providerString . $providerIndex . '.' . $fieldMappings['currency_code']])) {
        $parameters[$providerString . $providerIndex . '.' . $fieldMappings['currency_code']] = strtoupper($this->config['currency_code']);
      }
    }

    return $parameters;
  }

  /**
   * SetProviderCreditReversalDetails - sets the reverse provider credit details sent via the Refund API call.
   *
   * @param provider_id - [String]
   * @param credit_amount - [String]
   *
   * @optional currency_code - [String]
   */
  protected function setProviderCreditReversalDetails($parameters, $providerCreditInfo) {
    $providerIndex = 0;
    $providerString = 'ProviderCreditReversalList.member.';

    $fieldMappings = array(
      'provider_id' => 'ProviderId',
      'credit_reversal_amount' => 'CreditReversalAmount.Amount',
      'currency_code' => 'CreditReversalAmount.CurrencyCode',
    );

    foreach ($providerCreditInfo as $key => $value) {
      $value = array_change_key_case($value, CASE_LOWER);
      $providerIndex = $providerIndex + 1;

      foreach ($value as $param => $val) {
        if (array_key_exists($param, $fieldMappings) && trim($val) != '') {
          $parameters[$providerString . $providerIndex . '.' . $fieldMappings[$param]] = $val;
        }
      }

      // If currency code is not entered take it from the config array.
      if (empty($parameters[$providerString . $providerIndex . '.' . $fieldMappings['currency_code']])) {
        $parameters[$providerString . $providerIndex . '.' . $fieldMappings['currency_code']] = strtoupper($this->config['currency_code']);
      }
    }

    return $parameters;
  }

  /**
   * GetOrderReferenceDetails API call - Returns details about the Order Reference object and its current state.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751970
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_order_reference_id'] - [String]
   *
   * @optional requestParameters['address_consent_token'] - [String]
   * @optional requestParameters['access_token'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   *
   * You cannot pass both address_consent_token and access_token in
   * the same call or you will encounter a 400/"AmbiguousToken" error
   */
  public function getOrderReferenceDetails($requestParameters = array()) {

    $parameters['Action'] = 'GetOrderReferenceDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_order_reference_id' => 'AmazonOrderReferenceId',
      'address_consent_token' => 'AddressConsentToken',
      'access_token' => 'AccessToken',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);
    return ($responseObject);
  }

  /**
   * SetOrderReferenceDetails API call - Sets order reference details such as the order total and a description for the order.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751960
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_order_reference_id'] - [String]
   * @param requestParameters['amount'] - [String]
   * @param requestParameters['currency_code'] - [String]
   *
   * @optional requestParameters['platform_id'] - [String]
   * @optional requestParameters['seller_note'] - [String]
   * @optional requestParameters['seller_order_id'] - [String]
   * @optional requestParameters['store_name'] - [String]
   * @optional requestParameters['custom_information'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function setOrderReferenceDetails($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'SetOrderReferenceDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_order_reference_id' => 'AmazonOrderReferenceId',
      'amount' => 'OrderReferenceAttributes.OrderTotal.Amount',
      'currency_code' => 'OrderReferenceAttributes.OrderTotal.CurrencyCode',
      'platform_id' => 'OrderReferenceAttributes.PlatformId',
      'seller_note' => 'OrderReferenceAttributes.SellerNote',
      'seller_order_id' => 'OrderReferenceAttributes.SellerOrderAttributes.SellerOrderId',
      'store_name' => 'OrderReferenceAttributes.SellerOrderAttributes.StoreName',
      'custom_information' => 'OrderReferenceAttributes.SellerOrderAttributes.CustomInformation',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * ConfirmOrderReference API call - Confirms that the order reference is free of constraints and all required information has been set on the order reference.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751980
   *    * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_order_reference_id'] - [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function confirmOrderReference($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'ConfirmOrderReference';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_order_reference_id' => 'AmazonOrderReferenceId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * CancelOrderReference API call - Cancels a previously confirmed order reference.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751990
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_order_reference_id'] - [String]
   *
   * @optional requestParameters['cancelation_reason'] [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function cancelOrderReference($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'CancelOrderReference';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_order_reference_id' => 'AmazonOrderReferenceId',
      'cancelation_reason' => 'CancelationReason',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * CloseOrderReference API call - Confirms that an order reference has been fulfilled (fully or partially)
   * and that you do not expect to create any new authorizations on this order reference.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752000
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_order_reference_id'] - [String]
   *
   * @optional requestParameters['closure_reason'] [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function closeOrderReference($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'CloseOrderReference';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_order_reference_id' => 'AmazonOrderReferenceId',
      'closure_reason' => 'ClosureReason',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * CloseAuthorization API call - Closes an authorization.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752070
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_authorization_id'] - [String]
   *
   * @optional requestParameters['closure_reason'] [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function closeAuthorization($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'CloseAuthorization';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_authorization_id' => 'AmazonAuthorizationId',
      'closure_reason' => 'ClosureReason',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * Authorize API call - Reserves a specified amount against the payment method(s) stored in the order reference.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752010
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_order_reference_id'] - [String]
   * @param requestParameters['authorization_amount'] [String]
   * @param requestParameters['currency_code'] - [String]
   * @param requestParameters['authorization_reference_id'] [String]
   *
   * @optional requestParameters['capture_now'] [String]
   * @optional requestParameters['provider_credit_details'] - [array (array())]
   * @optional requestParameters['seller_authorization_note'] [String]
   * @optional requestParameters['transaction_timeout'] [String] - Defaults to 1440 minutes
   * @optional requestParameters['soft_descriptor'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function authorize($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'Authorize';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_order_reference_id' => 'AmazonOrderReferenceId',
      'authorization_amount' => 'AuthorizationAmount.Amount',
      'currency_code' => 'AuthorizationAmount.CurrencyCode',
      'authorization_reference_id' => 'AuthorizationReferenceId',
      'capture_now' => 'CaptureNow',
      'provider_credit_details' => array(),
      'seller_authorization_note' => 'SellerAuthorizationNote',
      'transaction_timeout' => 'TransactionTimeout',
      'soft_descriptor' => 'SoftDescriptor',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * GetAuthorizationDetails API call - Returns the status of a particular authorization and the total amount captured on the authorization.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752030
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_authorization_id'] [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function getAuthorizationDetails($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'GetAuthorizationDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_authorization_id' => 'AmazonAuthorizationId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * Capture API call - Captures funds from an authorized payment instrument.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752040
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_authorization_id'] - [String]
   * @param requestParameters['capture_amount'] - [String]
   * @param requestParameters['currency_code'] - [String]
   * @param requestParameters['capture_reference_id'] - [String]
   *
   * @optional requestParameters['provider_credit_details'] - [array (array())]
   * @optional requestParameters['seller_capture_note'] - [String]
   * @optional requestParameters['soft_descriptor'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function capture($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'Capture';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_authorization_id' => 'AmazonAuthorizationId',
      'capture_amount' => 'CaptureAmount.Amount',
      'currency_code' => 'CaptureAmount.CurrencyCode',
      'capture_reference_id' => 'CaptureReferenceId',
      'provider_credit_details' => array(),
      'seller_capture_note' => 'SellerCaptureNote',
      'soft_descriptor' => 'SoftDescriptor',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * GetCaptureDetails API call - Returns the status of a particular capture and the total amount refunded on the capture.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752060
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_capture_id'] - [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function getCaptureDetails($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'GetCaptureDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_capture_id' => 'AmazonCaptureId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * Refund API call - Refunds a previously captured amount.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752080
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_capture_id'] - [String]
   * @param requestParameters['refund_reference_id'] - [String]
   * @param requestParameters['refund_amount'] - [String]
   * @param requestParameters['currency_code'] - [String]
   *
   * @optional requestParameters['provider_credit_reversal_details'] - [array(array())]
   * @optional requestParameters['seller_refund_note'] [String]
   * @optional requestParameters['soft_descriptor'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function refund($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'Refund';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_capture_id' => 'AmazonCaptureId',
      'refund_reference_id' => 'RefundReferenceId',
      'refund_amount' => 'RefundAmount.Amount',
      'currency_code' => 'RefundAmount.CurrencyCode',
      'provider_credit_reversal_details' => array(),
      'seller_refund_note' => 'SellerRefundNote',
      'soft_descriptor' => 'SoftDescriptor',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * GetRefundDetails API call - Returns the status of a particular refund.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752100
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_refund_id'] - [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function getRefundDetails($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'GetRefundDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_refund_id' => 'AmazonRefundId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * GetServiceStatus API Call - Returns the operational status of the OffAmazonPayments API section.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201752110
   *
   * The GetServiceStatus operation returns the operational status of the OffAmazonPayments API
   * section of Amazon Marketplace Web Service (Amazon MWS).
   * Status values are GREEN, GREEN_I, YELLOW, and RED.
   *
   * @param requestParameters['merchant_id'] - [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function getServiceStatus($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'GetServiceStatus';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * CreateOrderReferenceForId API Call - Creates an order reference for the given object.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751670
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['id'] - [String]
   *
   * @optional requestParameters['inherit_shipping_address'] [Boolean]
   * @optional requestParameters['confirm_now'] - [Boolean]
   * @optional Amount (required when confirm_now is set to true) [String]
   * @optional requestParameters['currency_code'] - [String]
   * @optional requestParameters['seller_note'] - [String]
   * @optional requestParameters['seller_order_id'] - [String]
   * @optional requestParameters['store_name'] - [String]
   * @optional requestParameters['custom_information'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function createOrderReferenceForId($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'CreateOrderReferenceForId';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'id' => 'Id',
      'id_type' => 'IdType',
      'inherit_shipping_address' => 'InheritShippingAddress',
      'confirm_now' => 'ConfirmNow',
      'amount' => 'OrderReferenceAttributes.OrderTotal.Amount',
      'currency_code' => 'OrderReferenceAttributes.OrderTotal.CurrencyCode',
      'platform_id' => 'OrderReferenceAttributes.PlatformId',
      'seller_note' => 'OrderReferenceAttributes.SellerNote',
      'seller_order_id' => 'OrderReferenceAttributes.SellerOrderAttributes.SellerOrderId',
      'store_name' => 'OrderReferenceAttributes.SellerOrderAttributes.StoreName',
      'custom_information' => 'OrderReferenceAttributes.SellerOrderAttributes.CustomInformation',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * GetBillingAgreementDetails API Call - Returns details about the Billing Agreement object and its current state.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751690
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_billing_agreement_id'] - [String]
   *
   * @optional requestParameters['address_consent_token'] - [String]
   * @optional requestParameters['access_token'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   *
   * You cannot pass both address_consent_token and access_token in
   * the same call or you will encounter a 400/"AmbiguousToken" error
   */
  public function getBillingAgreementDetails($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'GetBillingAgreementDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
      'address_consent_token' => 'AddressConsentToken',
      'access_token' => 'AccessToken',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * SetBillingAgreementDetails API call - Sets Billing Agreement details such as a description of the agreement and other information about the seller.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751700
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_billing_agreement_id'] - [String]
   * @param requestParameters['amount'] - [String]
   * @param requestParameters['currency_code'] - [String]
   *
   * @optional requestParameters['platform_id'] - [String]
   * @optional requestParameters['seller_note'] - [String]
   * @optional requestParameters['seller_billing_agreement_id'] - [String]
   * @optional requestParameters['store_name'] - [String]
   * @optional requestParameters['custom_information'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function setBillingAgreementDetails($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'SetBillingAgreementDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
      'platform_id' => 'BillingAgreementAttributes.PlatformId',
      'seller_note' => 'BillingAgreementAttributes.SellerNote',
      'seller_billing_agreement_id' => 'BillingAgreementAttributes.SellerBillingAgreementAttributes.SellerBillingAgreementId',
      'custom_information' => 'BillingAgreementAttributes.SellerBillingAgreementAttributes.CustomInformation',
      'store_name' => 'BillingAgreementAttributes.SellerBillingAgreementAttributes.StoreName',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * ConfirmBillingAgreement API Call - Confirms that the Billing Agreement is free of constraints and all required information has been set on the Billing Agreement.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751710
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_billing_agreement_id'] - [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function confirmBillingAgreement($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'ConfirmBillingAgreement';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * ValidateBillignAgreement API Call - Validates the status of the Billing Agreement object and the payment method associated with it.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751720
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_billing_agreement_id'] - [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function validateBillingAgreement($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'ValidateBillingAgreement';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * AuthorizeOnBillingAgreement API call - Reserves a specified amount against the payment method(s) stored in the Billing Agreement.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751940
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_billing_agreement_id'] - [String]
   * @param requestParameters['authorization_reference_id'] [String]
   * @param requestParameters['authorization_amount'] [String]
   * @param requestParameters['currency_code'] - [String]
   *
   * @optional requestParameters['seller_authorization_note'] [String]
   * @optional requestParameters['transaction_timeout'] - Defaults to 1440 minutes
   * @optional requestParameters['capture_now'] [String]
   * @optional requestParameters['soft_descriptor'] - - [String]
   * @optional requestParameters['seller_note'] - [String]
   * @optional requestParameters['platform_id'] - [String]
   * @optional requestParameters['custom_information'] - [String]
   * @optional requestParameters['seller_order_id'] - [String]
   * @optional requestParameters['store_name'] - [String]
   * @optional requestParameters['inherit_shipping_address'] [Boolean] - Defaults to true
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function authorizeOnBillingAgreement($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'AuthorizeOnBillingAgreement';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
      'authorization_reference_id' => 'AuthorizationReferenceId',
      'authorization_amount' => 'AuthorizationAmount.Amount',
      'currency_code' => 'AuthorizationAmount.CurrencyCode',
      'seller_authorization_note' => 'SellerAuthorizationNote',
      'transaction_timeout' => 'TransactionTimeout',
      'capture_now' => 'CaptureNow',
      'soft_descriptor' => 'SoftDescriptor',
      'seller_note' => 'SellerNote',
      'platform_id' => 'PlatformId',
      'custom_information' => 'SellerOrderAttributes.CustomInformation',
      'seller_order_id' => 'SellerOrderAttributes.SellerOrderId',
      'store_name' => 'SellerOrderAttributes.StoreName',
      'inherit_shipping_address' => 'InheritShippingAddress',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * CloseBillingAgreement API Call - Returns details about the Billing Agreement object and its current state.
   *
   * @see https://pay.amazon.com/developer/documentation/apireference/201751950
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_billing_agreement_id'] - [String]
   *
   * @optional requestParameters['closure_reason'] [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function closeBillingAgreement($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'CloseBillingAgreement';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_billing_agreement_id' => 'AmazonBillingAgreementId',
      'closure_reason' => 'ClosureReason',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * Charge convenience method
   * Performs the API calls
   * 1. SetOrderReferenceDetails / SetBillingAgreementDetails
   * 2. ConfirmOrderReference / ConfirmBillingAgreement
   * 3. Authorize (with Capture) / AuthorizeOnBillingAgreeemnt (with Capture)
   *
   * @param requestParameters['merchant_id'] - [String]
   *
   * @param requestParameters['amazon_reference_id'] - [String] : Order Reference ID /Billing Agreement ID
   *   If requestParameters['amazon_reference_id'] is empty then the following is required,
   * @param requestParameters['amazon_order_reference_id'] - [String] : Order Reference ID
   *   or,
   * @param requestParameters['amazon_billing_agreement_id'] - [String] : Billing Agreement ID
   *
   * @param $requestParameters['charge_amount'] - [String] : Amount value to be captured
   * @param requestParameters['currency_code'] - [String] : Currency Code for the Amount
   * @param requestParameters['authorization_reference_id'] - [String]- Any unique string that needs to be passed
   *
   * @optional requestParameters['charge_note'] - [String] : Seller Note sent to the buyer
   * @optional requestParameters['transaction_timeout'] - [String] : Defaults to 1440 minutes
   * @optional requestParameters['charge_order_id'] - [String] : Custom Order ID provided
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function charge($requestParameters = array()) {

    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);
    $setParameters = $authorizeParameters = $confirmParameters = $requestParameters;

    if (!empty($requestParameters['amazon_order_reference_id'])) {
      $chargeType = 'OrderReference';
    }
    elseif (!empty($requestParameters['amazon_billing_agreement_id'])) {
      $chargeType = 'BillingAgreement';

    }
    elseif (!empty($requestParameters['amazon_reference_id'])) {
      switch (substr(strtoupper($requestParameters['amazon_reference_id']), 0, 1)) {
        case 'P':
        case 'S':
          $chargeType = 'OrderReference';
          $setParameters['amazon_order_reference_id'] = $requestParameters['amazon_reference_id'];
          $authorizeParameters['amazon_order_reference_id'] = $requestParameters['amazon_reference_id'];
          $confirmParameters['amazon_order_reference_id'] = $requestParameters['amazon_reference_id'];
          break;

        case 'B':
        case 'C':
          $chargeType = 'BillingAgreement';
          $setParameters['amazon_billing_agreement_id'] = $requestParameters['amazon_reference_id'];
          $authorizeParameters['amazon_billing_agreement_id'] = $requestParameters['amazon_reference_id'];
          $confirmParameters['amazon_billing_agreement_id'] = $requestParameters['amazon_reference_id'];
          break;

        default:
          throw new \Exception('Invalid Amazon Reference ID');
      }
    }
    else {
      throw new \Exception('key amazon_order_reference_id or amazon_billing_agreement_id is null and is a required parameter');
    }

    // Set the other parameters if the values are present.
    $setParameters['amount'] = !empty($requestParameters['charge_amount']) ? $requestParameters['charge_amount'] : '';
    $authorizeParameters['authorization_amount'] = !empty($requestParameters['charge_amount']) ? $requestParameters['charge_amount'] : '';

    $setParameters['seller_note'] = !empty($requestParameters['charge_note']) ? $requestParameters['charge_note'] : '';
    $authorizeParameters['seller_authorization_note'] = !empty($requestParameters['charge_note']) ? $requestParameters['charge_note'] : '';
    $authorizeParameters['seller_note'] = !empty($requestParameters['charge_note']) ? $requestParameters['charge_note'] : '';

    $setParameters['seller_order_id'] = !empty($requestParameters['charge_order_id']) ? $requestParameters['charge_order_id'] : '';
    $setParameters['seller_billing_agreement_id'] = !empty($requestParameters['charge_order_id']) ? $requestParameters['charge_order_id'] : '';
    $authorizeParameters['seller_order_id'] = !empty($requestParameters['charge_order_id']) ? $requestParameters['charge_order_id'] : '';

    $authorizeParameters['capture_now'] = !empty($requestParameters['capture_now']) ? $requestParameters['capture_now'] : FALSE;

    $response = $this->makeChargeCalls($chargeType, $setParameters, $confirmParameters, $authorizeParameters);
    return $response;
  }

  /**
   * MakeChargeCalls - makes API calls based off the charge type (OrderReference or BillingAgreement)
   */
  protected function makeChargeCalls($chargeType, $setParameters, $confirmParameters, $authorizeParameters) {
    switch ($chargeType) {

      case 'OrderReference':

        // Get the Order Reference details and feed the response object to the ResponseParser.
        $responseObj = $this->getOrderReferenceDetails($setParameters);

        // Call the function getOrderReferenceDetailsStatus in ResponseParser.php providing it the XML response
        // $oroStatus is an array containing the State of the Order Reference ID.
        $oroStatus = $responseObj->getOrderReferenceDetailsStatus($responseObj->toXml());

        if ($oroStatus['State'] === 'Draft') {
          $response = $this->setOrderReferenceDetails($setParameters);
          if ($this->success) {
            $this->confirmOrderReference($confirmParameters);
          }
        }

        $responseObj = $this->getOrderReferenceDetails($setParameters);

        // Check the Order Reference Status again before making the Authorization.
        $oroStatus = $responseObj->getOrderReferenceDetailsStatus($responseObj->toXml());

        if ($oroStatus['State'] === 'Open') {
          if ($this->success) {
            $response = $this->authorize($authorizeParameters);
          }
        }
        if (empty($response)) {
          return NULL;
        }
        elseif ($oroStatus['State'] != 'Open' && $oroStatus['State'] != 'Draft') {
          throw new \Exception('The Order Reference is in the ' . $oroStatus['State'] . " State. It should be in the Draft or Open State");
        }

        return $response;

      case 'BillingAgreement':

        // Get the Billing Agreement details and feed the response object to the ResponseParser.
        $responseObj = $this->getBillingAgreementDetails($setParameters);

        // Call the function getBillingAgreementDetailsStatus in ResponseParser.php providing it the XML response
        // $baStatus is an array containing the State of the Billing Agreement.
        $baStatus = $responseObj->getBillingAgreementDetailsStatus($responseObj->toXml());

        if ($baStatus['State'] === 'Draft') {
          $response = $this->setBillingAgreementDetails($setParameters);
          if ($this->success) {
            $response = $this->confirmBillingAgreement($confirmParameters);
          }
        }

        // Check the Billing Agreement status again before making the Authorization.
        $responseObj = $this->getBillingAgreementDetails($setParameters);
        $baStatus = $responseObj->getBillingAgreementDetailsStatus($responseObj->toXml());

        if ($this->success && $baStatus['State'] === 'Open') {
          $response = $this->authorizeOnBillingAgreement($authorizeParameters);
        }

        if (empty($response)) {
          return NULL;
        }
        elseif ($baStatus['State'] != 'Open' && $baStatus['State'] != 'Draft') {
          throw new \Exception('The Billing Agreement is in the ' . $baStatus['State'] . " State. It should be in the Draft or Open State");
        }

        return $response;
    }
  }

  /**
   * GetProviderCreditDetails API Call - Get the details of the Provider Credit.
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_provider_credit_id'] - [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function getProviderCreditDetails($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'GetProviderCreditDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_provider_credit_id' => 'AmazonProviderCreditId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * GetProviderCreditReversalDetails API Call - Get details of the Provider Credit Reversal.
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_provider_credit_reversal_id'] - [String]
   *
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function getProviderCreditReversalDetails($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'GetProviderCreditReversalDetails';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_provider_credit_reversal_id' => 'AmazonProviderCreditReversalId',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * ReverseProviderCredit API Call - Reverse the Provider Credit.
   *
   * @param requestParameters['merchant_id'] - [String]
   * @param requestParameters['amazon_provider_credit_id'] - [String]
   *
   * @optional requestParameters['credit_reversal_reference_id'] - [String]
   * @param requestParameters['credit_reversal_amount'] - [String]
   *
   * @optional requestParameters['currency_code'] - [String]
   * @optional requestParameters['credit_reversal_note'] - [String]
   * @optional requestParameters['mws_auth_token'] - [String]
   */
  public function reverseProviderCredit($requestParameters = array()) {
    $parameters = array();
    $parameters['Action'] = 'ReverseProviderCredit';
    $requestParameters = array_change_key_case($requestParameters, CASE_LOWER);

    $fieldMappings = array(
      'merchant_id' => 'SellerId',
      'amazon_provider_credit_id' => 'AmazonProviderCreditId',
      'credit_reversal_reference_id' => 'CreditReversalReferenceId',
      'credit_reversal_amount' => 'CreditReversalAmount.Amount',
      'currency_code' => 'CreditReversalAmount.CurrencyCode',
      'credit_reversal_note' => 'CreditReversalNote',
      'mws_auth_token' => 'MWSAuthToken',
    );

    $responseObject = $this->setParametersAndPost($parameters, $fieldMappings, $requestParameters);

    return ($responseObject);
  }

  /**
   * Create an Array of required parameters, sort them
   * Calculate signature and invoke the POST to the MWS Service URL.
   *
   * @param AWSAccessKeyId [String]
   * @param Version [String]
   * @param SignatureMethod [String]
   * @param Timestamp [String]
   * @param Signature [String]
   */
  private function calculateSignatureAndParametersToString($parameters = array()) {
    foreach ($parameters as $key => $value) {
      // Ensure that no unexpected type coercions have happened.
      if ($key === 'CaptureNow' || $key === 'ConfirmNow' || $key === 'InheritShippingAddress') {
        if (!is_bool($value)) {
          throw new \Exception($key . ' value ' . $value . ' is of type ' . gettype($value) . ' and should be a boolean value');
        }
      }

      // Ensure boolean values are outputed as 'true' or 'false'.
      if (is_bool($value)) {
        $parameters[$key] = json_encode($value);
      }
    }

    $parameters['AWSAccessKeyId'] = $this->config['access_key'];
    $parameters['Version'] = self::MWS_VERSION;
    $parameters['SignatureMethod'] = 'HmacSHA256';
    $parameters['SignatureVersion'] = 2;
    $parameters['Timestamp'] = gmdate("Y-m-d\TH:i:s.\\0\\0\\0\\Z", time());
    uksort($parameters, 'strcmp');

    $parameters['Signature'] = $this->signParameters($parameters);
    $parameters = drupal_http_build_query($parameters);

    return $parameters;
  }

  /**
   * Computes RFC 2104-compliant HMAC signature for request parameters
   * Implements AWS Signature, as per following spec:.
   *
   * If Signature Version is 0, it signs concatenated Action and Timestamp.
   *
   * If Signature Version is 1, it performs the following:
   *
   * Sorts all  parameters (including SignatureVersion and excluding Signature,
   * the value of which is being created), ignoring case.
   *
   * Iterate over the sorted list and append the parameter name (in original case)
   * and then its value. It will not URL-encode the parameter values before
   * constructing this string. There are no separators.
   *
   * If Signature Version is 2, string to sign is based on following:
   *
   *    1. The HTTP Request Method followed by an ASCII newline (%0A)
   *    2. The HTTP Host header in the form of lowercase host, followed by an ASCII newline.
   *    3. The URL encoded HTTP absolute path component of the URI
   *       (up to but not including the query string parameters);
   *       if this is empty use a forward '/'. This parameter is followed by an ASCII newline.
   *    4. The concatenation of all query string components (names and values)
   *       as UTF-8 characters which are URL encoded as per RFC 3986
   *       (hex characters MUST be uppercase), sorted using lexicographic byte ordering.
   *       Parameter names are separated from their values by the '=' character
   *       (ASCII character 61), even if the value is empty.
   *       Pairs of parameter and values are separated by the '&' character (ASCII code 38).
   */
  private function signParameters(array $parameters) {
    $signatureVersion = $parameters['SignatureVersion'];
    $stringToSign = NULL;
    if (2 === $signatureVersion) {
      $algorithm = "HmacSHA256";
      $parameters['SignatureMethod'] = $algorithm;
      $stringToSign = $this->calculateStringToSignV2($parameters);
    }
    else {
      throw new \Exception("Invalid Signature Version specified");
    }

    return $this->sign($stringToSign, $algorithm);
  }

  /**
   * Calculate String to Sign for SignatureVersion 2.
   *
   * @param array $parameters
   *   request parameters.
   *
   * @return String to Sign
   */
  protected function calculateStringToSignV2(array $parameters) {
    $data = 'POST';
    $data .= "\n";
    $data .= $this->mwsEndpointUrl;
    $data .= "\n";
    $data .= $this->mwsEndpointPath;
    $data .= "\n";
    $data .= drupal_http_build_query($parameters);
    return $data;
  }

  /**
   * Computes RFC 2104-compliant HMAC signature.
   *
   * @param $data
   * @param $algorithm
   *
   * @return string
   *
   * @throws \Exception
   */
  protected function sign($data, $algorithm) {
    if ($algorithm === 'HmacSHA1') {
      $hash = 'sha1';
    }
    else {
      if ($algorithm === 'HmacSHA256') {
        $hash = 'sha256';
      }
      else {
        throw new \Exception("Non-supported signing method specified");
      }
    }

    return base64_encode(hash_hmac($hash, $data, $this->config['secret_key'], TRUE));
  }

  /**
   * InvokePost takes the parameters and invokes the httpPost function to POST the parameters
   * Exponential retries on error 500 and 503
   * The response from the POST is an XML which is converted to Array.
   */
  protected function invokePost($parameters) {
    $this->success = FALSE;

    // Submit the request and read response body.
    try {
      $retries = 0;
      do {
        try {
          $this->constructUserAgentHeader();
          $httpCurlRequest = new AmazonPayHttpCurl($this->config);
          $response = $httpCurlRequest->httpPost($this->mwsServiceUrl, $this->userAgent, $parameters);
          $curlResponseInfo = $httpCurlRequest->getCurlResponseInfo();
          $statusCode = $curlResponseInfo["http_code"];
          $this->logMessage($this->userAgent);
          $response = array(
            'Status' => $statusCode,
            'ResponseBody' => $response,
          );

          $statusCode = $response['Status'];
          if ($statusCode == 200) {
            $shouldRetry = FALSE;
            $this->success = TRUE;
          }
          elseif ($statusCode == 500 || $statusCode == 503) {

            $shouldRetry = TRUE;
            if ($shouldRetry && strtolower($this->config['handle_throttle'])) {
              $this->pauseOnRetry(++$retries, $statusCode);
            }
          }
          else {
            $shouldRetry = FALSE;
          }
        }
        catch (\Exception $e) {
          throw $e;
        }
      } while ($shouldRetry);
    }
    catch (\Exception $se) {
      throw $se;
    }

    return $response;
  }

  /**
   * Exponential sleep on failed request
   * Up to three retries will occur if first reqest fails
   * after 1.0 second, 2.2 seconds, and finally 7.0 seconds.
   *
   * @param int $retries
   *
   * @throws Exception if maximum number of retries has been reached
   */
  protected function pauseOnRetry($retries, $status) {
    if ($retries <= self::MAX_ERROR_RETRY) {
      // PHP delays are in microseconds (1 million microsecond = 1 sec)
      // 1st delay is (4^1) * 100000 + 600000 = 0.4 + 0.6 second = 1.0 sec
      // 2nd delay is (4^2) * 100000 + 600000 = 1.6 + 0.6 second = 2.2 sec
      // 3rd delay is (4^3) * 100000 + 600000 = 6.4 + 0.6 second = 7.0 sec.
      $delay = (int) (pow(4, $retries) * 100000) + 600000;
      usleep($delay);
    }
    else {
      throw new \Exception('Error Code: ' . $status . PHP_EOL . 'Maximum number of retry attempts - ' . $retries . ' reached');
    }
  }

  /**
   * Create the User Agent Header sent with the POST request.
   */
  protected function constructUserAgentHeader() {
    $this->userAgent = 'drupalcommerce-sdk/1.0 (';
    if (($this->config['application_name']) || ($this->config['application_version'])) {
      if ($this->config['application_name']) {
        $quoted_application_name = preg_replace('/ {2,}|\s/', ' ', $this->config['application_name']);
        $quoted_application_name = preg_replace('/\\\\/', '\\\\\\\\', $quoted_application_name);
        $quoted_application_name = preg_replace('/\//', '\\/', $quoted_application_name);
        $this->userAgent .= $quoted_application_name;
        if ($this->config['application_version']) {
          $this->userAgent .= '/';
        }
      }

      if ($this->config['application_version']) {
        $quoted_user_agent_string = preg_replace('/ {2,}|\s/', ' ', $this->config['application_version']);
        $quoted_user_agent_string = preg_replace('/\\\\/', '\\\\\\\\', $quoted_user_agent_string);
        $quoted_user_agent_string = preg_replace('/\\(/', '\\(', $quoted_user_agent_string);
        $this->userAgent .= $quoted_user_agent_string;
      }
      $this->userAgent .= '; ';
    }
    $this->userAgent .= 'PHP/' . phpversion() . '; ';
    $this->userAgent .= php_uname('s') . '/' . php_uname('m') . '/' . php_uname('r');
    $this->userAgent .= ')';
  }

  /**
   * Computes RFC 2104-compliant HMAC signature.
   *
   * @param $stringToSign
   * @param $secretKey
   *
   * @return string
   */
  public static function getSignature($stringToSign, $secretKey) {
    return base64_encode(hash_hmac('sha256', $stringToSign, $secretKey, TRUE));
  }

}
