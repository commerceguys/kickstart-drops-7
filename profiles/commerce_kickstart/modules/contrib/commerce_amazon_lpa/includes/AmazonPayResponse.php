<?php

/**
 * @file
 * Methods provided to convert the Response from the POST to XML, Array or JSON.
 */

/**
 * Response handling class.
 */
class AmazonPayResponse {

  public $response = NULL;

  /**
   * Constructs a new AmazonPayResponse object.
   */
  public function __construct($response = NULL) {
    $this->response = $response;
  }

  /**
   * Returns the XML portion of the response.
   */
  public function toXml() {
    return $this->response['ResponseBody'];
  }

  /**
   * Converts XML into JSON.
   *
   * @return string
   *   The response as JSON.
   */
  public function toJson() {
    $response = $this->simpleXmlObject();
    return drupal_json_encode($response);
  }

  /**
   * Converts XML into associative array.
   *
   * @return array
   *   The response as an array.
   */
  public function toArray() {
    $response = $this->simpleXmlObject();

    // Converting the SimpleXMLElement Object to array()
    $response = drupal_json_encode($response);

    return drupal_json_decode($response);
  }

  /**
   * Turns the response into an XML object.
   */
  protected function simpleXmlObject() {
    $response = $this->response;

    // Getting the HttpResponse Status code to the output as a string.
    $status = strval($response['Status']);

    // Getting the Simple XML element object of the XML Response Body.
    $response = simplexml_load_string((string) $response['ResponseBody']);

    // Adding the HttpResponse Status code to the output as a string.
    $response->addChild('ResponseStatus', $status);

    return $response;
  }

  /**
   * Get the status of the Order Reference ID.
   */
  public function getOrderReferenceDetailsStatus($response) {
    $oroStatus = $this->getStatus('GetORO', '//GetORO:OrderReferenceStatus', $response);

    return $oroStatus;
  }

  /**
   * Get the status of the BillingAgreement.
   */
  public function getBillingAgreementDetailsStatus($response) {
    $baStatus = $this->getStatus('GetBA', '//GetBA:BillingAgreementStatus', $response);

    return $baStatus;
  }

  /**
   * Gets the request status.
   */
  protected function getStatus($type, $path, $response) {
    $data = new \SimpleXMLElement($response);
    $namespaces = $data->getNamespaces(TRUE);
    foreach ($namespaces as $key => $value) {
      $namespace = $value;
    }
    $data->registerXPathNamespace($type, $namespace);
    foreach ($data->xpath($path) as $value) {
      $status = json_decode(json_encode((array) $value), TRUE);
    }

    return $status;
  }

}
