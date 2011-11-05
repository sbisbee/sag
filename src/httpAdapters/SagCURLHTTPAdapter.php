<?php
class SagCURLHTTPAdapter extends SagHTTPAdapter {
  private $ch;

  public function __construct($host, $port) {
    parent::__construct($host, $port);

    $this->ch = curl_init();
  }

  public function procPacket($method, $url, $data = null, $headers = array()) {
    // the base cURL options
    $opts = array(
      CURLOPT_URL => "{$this->proto}://{$this->host}:{$this->port}{$url}",
      CURLOPT_PORT => $this->port,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HEADER => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method
    );

    // cURL wants the headers as an array of strings, not an assoc array
    if(sizeof($headers) > 0) {
      $curlHeaders = array();

      foreach($headers as $k => $v) {
        $curlHeaders[] = "$k: $v";
      }

      $opts[CURLOPT_HTTPHEADER] = $curlHeaders;
    }

    if($data) {
      $opts[CURLOPT_POSTFIELDS] = $data;
    }

    if($method == 'HEAD') {
      $opts[CURLOPT_NOBODY] = true;
    }

    curl_setopt_array($this->ch, $opts);

    $chResponse = curl_exec($this->ch);

    if($chResponse !== false) {
      //prepare the response object
      $response = new stdClass();
      $response->headers = new stdClass();
      $response->headers->_HTTP = new stdClass();
      $response->body = '';

      //split headers and body
      list($headers, $response->body) = explode("\r\n\r\n", $chResponse);

      //split up the headers
      $headers = explode("\r\n", $headers);

      for($i = 0; $i < sizeof($headers); $i++) {
        //first element will always be the HTTP status line
        if($i === 0) {
          $response->headers->_HTTP->raw = $headers[$i];

          preg_match('(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $headers[$i], $match);

          $response->headers->_HTTP->version = $match['version'];
          $response->headers->_HTTP->status = $match['status'];
          $response->status = $match['status'];
        }
        else {
          $line = explode(':', $headers[$i], 2);
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
