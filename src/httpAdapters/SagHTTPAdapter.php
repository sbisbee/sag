<?php
/**
 * Provides a common interface for Sag to connect to CouchDB over HTTP,
 * allowing for different drivers to be used thereby controling your project's
 * dependencies.
 *
 * @version 0.7.0
 * @package HTTP
 */
abstract class SagHTTPAdapter {
  public $decodeResp = true;

  protected $host;
  protected $port;
  protected $proto = 'http'; //http or https
  protected $sslCertPath;

  protected $socketOpenTimeout;
  protected $socketRWTimeoutSeconds;
  protected $socketRWTimeoutMicroseconds;

  public function __construct($host = "127.0.0.1", $port = "5984") {
    $this->host = $host;
    $this->port = $port;
  }

  protected function makeResult($response, $method) {
    //Make sure we got the complete response.
    if(
      $method != 'HEAD' &&
      isset($response->headers->{'Content-Length'}) &&
      strlen($response->body) != $response->headers->{'Content-Length'}
    ) {
      throw new SagException('Unexpected end of packet.');
    }

    /*
     * HEAD requests can return an HTTP response code >=400, meaning that there
     * was a CouchDB error, but we don't get a $response->body->error because
     * HEAD responses don't have bodies.
     *
     * And we do this before the json_decode() because even running
     * json_decode() on undefined can take longer than calling it on a JSON
     * string. So no need to run any of the $json code.
     */
    if($method == 'HEAD') {
      if($response->headers->_HTTP->status >= 400) {
        throw new SagCouchException('HTTP/CouchDB error without message body', $response->headers->_HTTP->status);
      }

      //no else needed - just going to return below
    }
    else {
      /*
       * $json won't be set if invalid JSON is sent back to us. This will most
       * likely happen if we're GET'ing an attachment that isn't JSON (ex., a
       * picture or plain text). Don't be fooled by storing a PHP string in an
       * attachment as text/plain and then expecting it to be parsed by
       * json_decode().
       */
      $json = json_decode($response->body);

      if(isset($json)) {
        /*
         * Check for an error from CouchDB regardless of whether they want JSON
         * returned.
         */
        if(!empty($json->error)) {
          throw new SagCouchException("{$json->error} ({$json->reason})", $response->headers->_HTTP->status);
        }

        $response->body = ($this->decodeResp) ? $json : $response->body;
      }
    }

    return $response;
  }

  protected function parseCookieString($cookieStr) {
    $cookies = new stdClass();

    foreach(explode('; ', $cookieStr) as $cookie) {
      $crumbs = explode('=', $cookie);
      $cookies->{trim($crumbs[0])} = trim($crumbs[1]);
    }

    return $cookies;
  }

  /**
   * Processes the packet, returning the server's response.
   */
  abstract public function procPacket($method, $url, $data = null, $headers = array());

  /**
   * Whether to use HTTPS or not.
   */
  public function useSSL($use) {
    $this->proto = 'http' . (($use) ? 's' : '');
  }

  /**
   * Sets the location of the CA file.
   */
  public function setSSLCert($path) {
    $this->sslCertPath = $path;
  }

  /**
   * Returns whether Sag is using SSL.
   */
  public function usingSSL() {
    return $this->proto === 'https';
  }

  /**
   * Sets how long Sag should wait to establish a connection to CouchDB.
   *
   * @param int $seconds
   */
  public function setOpenTimeout($seconds) {
    if(!is_int($seconds) || $seconds < 1) {
      throw new SagException('setOpenTimeout() expects a positive integer.');
    }

    $this->socketOpenTimeout = $seconds;
  }

  /**
   * Set how long we should wait for an HTTP request to be executed.
   *
   * @param int $seconds The number of seconds.
   * @param int $microseconds The number of microseconds.
   */
  public function setRWTimeout($seconds, $microseconds) {
    if(!is_int($microseconds) || $microseconds < 0) {
      throw new SagException('setRWTimeout() expects $microseconds to be an integer >= 0.');
    }

    //TODO make this better, including checking $microseconds
    //$seconds can be 0 if $microseconds > 0
    if(
      !is_int($seconds) ||
      (
        (!$microseconds && $seconds < 1) ||
        ($microseconds && $seconds < 0)
      )
    ) {
      throw new SagException('setRWTimeout() expects $seconds to be a positive integer.');
    }

    $this->socketRWTimeoutSeconds = $seconds;
    $this->socketRWTimeoutMicroseconds = $microseconds;
  }
}
?>
