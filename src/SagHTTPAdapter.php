<?php
/**
 * Provides a common interface for Sag to connect to CouchDB over HTTP,
 * allowing for different drivers to be used thereby controling your project's
 * dependencies.
 *
 * @version 0.6.0
 * @package HTTP
 */
abstract class SagHTTPAdapter {
  public $decodeJSON = true;

  protected $host;
  protected $port;

  public function __construct($host = "127.0.0.1", $port = "5984") {
    $this->host = $host;
    $this->port = $port;
  }

  /**
   * Processes the packet, returning the server's response.
   */
  abstract public function procPacket($method, $url, $data = null, $headers = array());

  protected function makeResult($response) {
    /*
     * $json won't be set if invalid JSON is sent back to us. This will most
     * likely happen if we're GET'ing an attachment that isn't JSON (ex., a
     * picture or plain text). Don't be fooled by storing a PHP string in an
     * attachment as text/plain and then expecting it to be parsed by
     * json_decode().
     */
    $json = json_decode($response->body);

    if(isset($json))
    {
      // Check for an error from CouchDB regardless of whether they want JSON
      // returned.
      if(!empty($json->error))
        throw new SagCouchException("{$json->error} ({$json->reason})", $response->headers->_HTTP->status);

      $response->body = ($this->decodeResp) ? $json : $response->body;
    }

    return $response;
  }
}
?>
