<?php

/**
 * @file
 * Contains the AmazonPayIpnHandler class.
 */

/**
 * Handles the IPN response.
 */
class AmazonPayIpnHandler {

  private $headers = NULL;

  private $body = NULL;

  private $snsMessage = NULL;

  private $fields = array();

  private $signatureFields = array();

  private $certificate = NULL;

  private $expectedCnName = 'sns.amazonaws.com';

  private $defaultHostPattern = '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/';

  private $ipnConfig = array(
    'cabundle_file' => NULL,
    'proxy_host' => NULL,
    'proxy_port' => -1,
    'proxy_username' => NULL,
    'proxy_password' => NULL,
  );

  /**
   * Constructs a new AmazonPayIpnHandler object.
   */
  public function __construct($headers, $body, $ipnConfig = NULL) {
    $this->headers = array_change_key_case($headers, CASE_LOWER);
    $this->body = $body;

    if ($ipnConfig != NULL) {
      $this->checkConfigKeys($ipnConfig);
    }

    // Get the list of fields that we are interested in.
    $this->fields = array(
      "Timestamp" => TRUE,
      "Message" => TRUE,
      "MessageId" => TRUE,
      "Subject" => FALSE,
      "TopicArn" => TRUE,
      "Type" => TRUE,
    );
    $this->validateHeaders();
    $this->getMessage();
    $this->checkForCorrectMessageType();
    $this->constructAndVerifySignature();
  }

  /**
   * Checks config key integrity.
   */
  protected function checkConfigKeys($ipnConfig) {
    $ipnConfig = array_change_key_case($ipnConfig, CASE_LOWER);
    $ipnConfig = $this->trimArray($ipnConfig);

    foreach ($ipnConfig as $key => $value) {
      if (array_key_exists($key, $this->ipnConfig)) {
        $this->ipnConfig[$key] = $value;
      }
      else {
        throw new \Exception('Key ' . $key . ' is either not part of the configuration or has incorrect Key name.
                check the ipnConfig array key names to match your key names of your config array ', 1);
      }
    }
  }

  /**
   * Helper function to log data within the Client.
   */
  protected function logMessage($message) {
    commerce_amazon_lpa_add_debug_log($message, array(), WATCHDOG_INFO);
  }

  /**
   * Sets the value for the key if the key exists in ipnConfig.
   */
  public function __set($name, $value) {
    if (array_key_exists(strtolower($name), $this->ipnConfig)) {
      $this->ipnConfig[$name] = $value;
    }
    else {
      throw new \Exception("Key " . $name . " is not part of the configuration", 1);
    }
  }

  /**
   * Gets IPN config values.
   */
  public function __get($name) {
    if (array_key_exists(strtolower($name), $this->ipnConfig)) {
      return $this->ipnConfig[$name];
    }
    else {
      throw new \Exception("Key " . $name . " was not found in the configuration", 1);
    }
  }

  /**
   * Trim the input Array key values .
   */
  private function trimArray($array) {
    foreach ($array as $key => $value) {
      $array[$key] = trim($value);
    }
    return $array;
  }

  /**
   * Validates incoming IPN headers.
   */
  protected function validateHeaders() {
    // Quickly check that this is a sns message.
    if (!array_key_exists('x-amz-sns-message-type', $this->headers)) {
      throw new \Exception("Error with message - header " . "does not contain x-amz-sns-message-type header");
    }

    if ($this->headers['x-amz-sns-message-type'] !== 'Notification') {
      throw new \Exception("Error with message - header x-amz-sns-message-type is not " . "Notification, is " . $this->headers['x-amz-sns-message-type']);
    }
  }

  /**
   * Gets the message.
   */
  protected function getMessage() {
    $this->snsMessage = json_decode($this->body, TRUE);

    $json_error = json_last_error();

    if ($json_error != 0) {
      $errorMsg = "Error with message - content is not in json format" . $this->getErrorMessageForJsonError($json_error) . " " . $this->snsMessage;
      throw new \Exception($errorMsg);
    }
  }

  /**
   * Convert a json error code to a descriptive error message.
   *
   * @param int $json_error
   *   The message code.
   *
   * @return string
   *   The error message.
   */
  private function getErrorMessageForJsonError($json_error) {
    switch ($json_error) {
      case JSON_ERROR_DEPTH:
        return " - maximum stack depth exceeded.";

      case JSON_ERROR_STATE_MISMATCH:
        return " - invalid or malformed JSON.";

      case JSON_ERROR_CTRL_CHAR:
        return " - control character error.";

      case JSON_ERROR_SYNTAX:
        return " - syntax error.";

      default:
        return ".";
    }
  }

  /**
   * CheckForCorrectMessageType()
   *
   * Checks if the Field [Type] is set to ['Notification']
   * Gets the value for the fields marked true in the fields array
   * Constructs the signature string.
   */
  private function checkForCorrectMessageType() {
    $type = $this->getMandatoryField("Type");
    if (strcasecmp($type, "Notification") != 0) {
      throw new \Exception("Error with SNS Notification - unexpected message with Type of " . $type);
    }

    if (strcmp($this->getMandatoryField("Type"), "Notification") != 0) {
      throw new \Exception("Error with signature verification - unable to verify " . $this->getMandatoryField("Type") . " message");
    }
    else {

      // Sort the fields into byte order based on the key name(A-Za-z)
      ksort($this->fields);

      // Extract the key value pairs and sort in byte order.
      $signatureFields = array();
      foreach ($this->fields as $fieldName => $mandatoryField) {
        if ($mandatoryField) {
          $value = $this->getMandatoryField($fieldName);
        }
        else {
          $value = $this->getField($fieldName);
        }

        if (!is_null($value)) {
          array_push($signatureFields, $fieldName);
          array_push($signatureFields, $value);
        }
      }

      /* Create the signature string - key / value in byte order
       * delimited by newline character + ending with a new line character
       */
      $this->signatureFields = implode("\n", $signatureFields) . "\n";
    }
  }

  /**
   * Ensures that the URL of the certificate is one belonging to AWS.
   *
   * @param string $url
   *   Certificate URL.
   *
   * @throws \Exception
   */
  protected function validateUrl($url) {
    $parsed = parse_url($url);
    if (empty($parsed['scheme'])
      || empty($parsed['host'])
      || $parsed['scheme'] !== 'https'
      || substr($url, -4) !== '.pem'
      || !preg_match($this->defaultHostPattern, $parsed['host'])
    ) {
      throw new \Exception(
        'The certificate is located on an invalid domain.'
      );
    }
  }

  /**
   * Verify that the signature is correct.
   *
   * @throws Exception
   */
  private function constructAndVerifySignature() {
    $signature = base64_decode($this->getMandatoryField("Signature"));
    $certificatePath = $this->getMandatoryField("SigningCertURL");
    $this->validateUrl($certificatePath);
    $this->certificate = $this->getCertificate($certificatePath);

    $result = $this->verifySignatureIsCorrectFromCertificate($signature);
    if (!$result) {
      throw new \Exception("Unable to match signature from remote server: signature of " . $this->getCertificate($certificatePath) . " , SigningCertURL of " . $this->getMandatoryField("SigningCertURL") . " , SignatureOf " . $this->getMandatoryField("Signature"));
    }
  }

  /**
   * Gets the certificate from the $certificatePath using Curl.
   *
   * @return mixed
   *   The response.
   */
  private function getCertificate($certificatePath) {
    $httpCurlRequest = new AmazonPayHttpCurl($this->ipnConfig);
    $response = $httpCurlRequest->httpGet($certificatePath);
    return $response;
  }

  /**
   * Verify that the signature is correct for the given data and public key.
   *
   * @param string $signature
   *   The decoded signature to compare against.
   */
  public function verifySignatureIsCorrectFromCertificate($signature) {
    $certKey = openssl_get_publickey($this->certificate);

    if ($certKey === FALSE) {
      throw new \Exception("Unable to extract public key from cert");
    }

    try {
      $certInfo = openssl_x509_parse($this->certificate, TRUE);
      $certSubject = $certInfo["subject"];

      if (is_null($certSubject)) {
        throw new \Exception("Error with certificate - subject cannot be found");
      }
    }
    catch (\Exception $ex) {
      throw new \Exception("Unable to verify certificate - error with the certificate subject", NULL, $ex);
    }

    if (strcmp($certSubject["CN"], $this->expectedCnName)) {
      throw new \Exception("Unable to verify certificate issued by Amazon - error with certificate subject");
    }

    $result = -1;
    try {
      $result = openssl_verify($this->signatureFields, $signature, $certKey, OPENSSL_ALGO_SHA1);
    }
    catch (\Exception $ex) {
      throw new \Exception("Unable to verify signature - error with the verification algorithm", NULL, $ex);
    }

    return ($result > 0);
  }

  /**
   * Extract the mandatory field from the message and return the contents.
   *
   * @param string $fieldName
   *   Name of the field to extract.
   *
   * @return string
   *   The field contents if found.
   *
   * @throws \Exception
   */
  protected function getMandatoryField($fieldName) {
    $value = $this->getField($fieldName);
    if (is_null($value)) {
      throw new \Exception("Error with json message - mandatory field " . $fieldName . " cannot be found");
    }
    return $value;
  }

  /**
   * Extract the field if present, return null if not defined.
   *
   * @param string $fieldName
   *   Name of the field to extract.
   *
   * @return string
   *   The field contents if found, NULL otherwise.
   */
  protected function getField($fieldName) {
    if (array_key_exists($fieldName, $this->snsMessage)) {
      return $this->snsMessage[$fieldName];
    }
    else {
      return NULL;
    }
  }

  /**
   * JSON decode the raw [Message] portion of the IPN .
   */
  public function returnMessage() {
    return json_decode($this->snsMessage['Message'], TRUE);
  }

  /**
   * Converts IPN [Message] field to JSON.
   *
   * Has child elements
   * ['NotificationData'] [XML] - API call XML notification data.
   *
   * @return string
   *   The response in JSON format.
   */
  public function toJson() {
    $response = $this->simpleXmlObject();

    // Merging the remaining fields with the response.
    $remainingFields = $this->getRemainingIpnFields();
    $responseArray = array_merge($remainingFields, (array) $response);

    // Converting to JSON format.
    $response = json_encode($responseArray);

    return $response;
  }

  /**
   * Converts IPN [Message] field to associative array.
   *
   * @return array
   *   The response in array format
   */
  public function toArray() {
    $response = $this->simpleXmlObject();

    // Converting the SimpleXMLElement Object to array()
    $response = json_encode($response);
    $response = json_decode($response, TRUE);

    // Merging the remaining fields with the response array.
    $remainingFields = $this->getRemainingIpnFields();
    $response = array_merge($remainingFields, $response);

    return $response;
  }

  /**
   * Add remaining fields to the datatype.
   *
   * Has child elements
   * ['NotificationData'] [XML] - API call XML response data
   * Convert to SimpleXML element object
   * Type - Notification
   * MessageId -  ID of the Notification
   * Topic ARN - Topic of the IPN.
   *
   * @return array
   *   The response in array format.
   */
  private function simpleXmlObject() {
    $ipnMessage = $this->returnMessage();
    $response = simplexml_load_string((string) $ipnMessage['NotificationData']);
    $response->addChild('Type', $this->snsMessage['Type']);
    $response->addChild('MessageId', $this->snsMessage['MessageId']);
    $response->addChild('TopicArn', $this->snsMessage['TopicArn']);
    return $response;
  }

  /**
   * Gets the remaining fields of the IPN.
   */
  private function getRemainingIpnFields() {
    $ipnMessage = $this->returnMessage();

    $remainingFields = array(
      'NotificationReferenceId' => $ipnMessage['NotificationReferenceId'],
      'NotificationType' => $ipnMessage['NotificationType'],
      'SellerId' => $ipnMessage['SellerId'],
      'ReleaseEnvironment' => $ipnMessage['ReleaseEnvironment'],
    );

    return $remainingFields;
  }

}
