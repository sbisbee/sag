<?php
/**
 * Uses the PHP cURL bindings for HTTP communication with CouchDB. This gives
 * you more advanced features, like SSL supports, with the cost of an
 * additional dependency that your shared hosting environment might now have. 
 *
 * @version 0.8.0
 * @package HTTP
 */
require_once('SagHTTPAdapter.php');

class SagCURLHTTPAdapter extends SagHTTPAdapter {
  private $ch;

  public function __construct($host, $port) {
    if(!extension_loaded('curl')) {
      throw new SagException('Sag cannot use cURL on this system: the PHP cURL extension is not installed.');
    }

    parent::__construct($host, $port);

    $this->ch = curl_init();
  }

  public function procPacket($method, $url, $data = null, $headers = array(), $specialHost = null, $specialPort = null) {
    // the base cURL options
    $opts = array(
      CURLOPT_URL => "{$this->proto}://{$this->host}:{$this->port}{$url}",
      CURLOPT_PORT => $this->port,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HEADER => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_NOBODY => false,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method
    );

    // cURL wants the headers as an array of strings, not an assoc array
    if(is_array($headers) && sizeof($headers) > 0) {
      $opts[CURLOPT_HTTPHEADER] = array();

      foreach($headers as $k => $v) {
        $opts[CURLOPT_HTTPHEADER][] = "$k: $v";
      }
    }

    // send data through cURL's poorly named opt
    if($data) {
      $opts[CURLOPT_POSTFIELDS] = $data;
    }

    // special considerations for HEAD requests
    if($method == 'HEAD') {
      $opts[CURLOPT_NOBODY] = true;
    }

    // connect timeout
    if(is_int($this->socketOpenTimeout)) {
      $opts[CURLOPT_CONNECTTIMEOUT] = $this->socketOpenTimeout;
    }

    // exec timeout (seconds)
    if(is_int($this->socketRWTimeoutSeconds)) {
      $opts[CURLOPT_TIMEOUT] = $this->socketRWTimeoutSeconds;
    }

    // exec timeout (ms)
    if(is_int($this->socketRWTimeoutMicroseconds)) {
      $opts[CURLOPT_TIMEOUT_MS] = $this->socketRWTimeoutMicroseconds;
    }

    // SSL support: don't verify unless we have a cert set
    if($this->proto === 'https') {
      if(!$this->sslCertPath) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
      }
      else {
        $opts[CURLOPT_SSL_VERIFYPEER] = true;
        $opts[CURLOPT_SSL_VERIFYHOST] = true;
        $opts[CURLOPT_CAINFO] = $this->sslCertPath;
      }
    }

    curl_setopt_array($this->ch, $opts);

    $chResponse = curl_exec($this->ch);

    if($chResponse !== false) {
      // prepare the response object
      $response = new stdClass();
      $response->headers = new stdClass();
      $response->headers->_HTTP = new stdClass();
      $response->body = '';

      // split headers and body
      list($headers, $response->body) = explode("\r\n\r\n", $chResponse);

      // split up the headers
      $headers = explode("\r\n", $headers);

      for($i = 0; $i < sizeof($headers); $i++) {
        // first element will always be the HTTP status line
        if($i === 0) {
          $response->headers->_HTTP->raw = $headers[$i];

          preg_match('(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $headers[$i], $match);

          $response->headers->_HTTP->version = $match['version'];
          $response->headers->_HTTP->status = $match['status'];
          $response->status = $match['status'];
        }
        else {
          $line = explode(':', $headers[$i], 2);
          $line[0] = strtolower($line[0]);
          $response->headers->$line[0] = ltrim($line[1]);

          if($line[0] == 'Set-Cookie') {
            $response->cookies = $this->parseCookieString($line[1]);
          }
        }
      }
    }
    else if(curl_errno($this->ch)) {
      throw new SagException('cURL error #' . curl_errno($this->ch) . ': ' . curl_error($this->ch));
    }
    else {
      throw new SagException('cURL returned false without providing an error.');
    }

    return self::makeResult($response, $method);
  }
}
?>
