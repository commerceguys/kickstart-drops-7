<?php

/**
 * @file Class HttpCurl
 * Handles Curl POST function for all requests.
 */

/**
 *
 */
class AmazonPayHttpCurl {

  private $config = array();

  private $header = FALSE;

  private $accessToken = NULL;

  private $curlResponseInfo = NULL;

  private $headerArray = array();

  /**
   * Takes user configuration array as input
   * Takes configuration for API call or IPN config.
   */
  public function __construct($config = NULL) {
    $this->config = $config;
  }

  /**
   * Setter for boolean header to get the user info .
   */
  public function setHttpHeader() {
    $this->header = TRUE;
  }

  /**
   * Setter for Access token to get the user info .
   */
  public function setAccessToken($accesstoken) {
    $this->accessToken = $accesstoken;
  }

  /**
   * Add the common Curl Parameters to the curl handler.

   * Also checks for optional parameters if provided in the config
   * config['cabundle_file']
   * config['proxy_port']
   * config['proxy_host']
   * config['proxy_username']
   * config['proxy_password'].
   */
  protected function commonCurlParams($url, $userAgent) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_PORT, 443);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    if (!is_null($this->config['cabundle_file'])) {
      curl_setopt($ch, CURLOPT_CAINFO, $this->config['cabundle_file']);
    }

    if (!empty($userAgent)) {
      curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    }

    if ($this->config['proxy_host'] != NULL && $this->config['proxy_port'] != -1) {
      curl_setopt($ch, CURLOPT_PROXY, $this->config['proxy_host'] . ':' . $this->config['proxy_port']);
    }

    if ($this->config['proxy_username'] != NULL && $this->config['proxy_password'] != NULL) {
      curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->config['proxy_username'] . ':' . $this->config['proxy_password']);
    }

    return $ch;
  }

  /**
   * Performs an HTTP POST request.
   */
  public function httpPost($url, $userAgent = NULL, $parameters = NULL) {
    $ch = $this->commonCurlParams($url, $userAgent);

    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);

    $response = $this->execute($ch);
    return $response;
  }

  /**
   * Performs an HTTP GET request.
   */
  public function httpGet($url, $userAgent = NULL) {
    $ch = $this->commonCurlParams($url, $userAgent);

    // Setting the HTTP header with the Access Token only for Getting user info.
    if ($this->header) {
      $this->headerArray[] = 'Authorization: bearer ' . $this->accessToken;
    }

    $response = $this->execute($ch);
    return $response;
  }

  /**
   * Execute Curl request.
   */
  private function execute($ch) {
    // Ensure we never send the "Expect: 100-continue" header.
    $this->headerArray[] = 'Expect:';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headerArray);

    $response = curl_exec($ch);
    if ($response === FALSE) {
      $error_msg = "Unable to post request, underlying exception of " . curl_error($ch);
      curl_close($ch);
      throw new \Exception($error_msg);
    }
    else {
      $this->curlResponseInfo = curl_getinfo($ch);
    }
    curl_close($ch);
    return $response;
  }

  /**
   * Get the output of Curl Getinfo .
   */
  public function getCurlResponseInfo() {
    return $this->curlResponseInfo;
  }

}
