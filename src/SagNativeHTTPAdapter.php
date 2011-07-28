<?php
require_once('SagHTTPAdapter.php');

/**
 * Uses native PHP sockets to communicate with CouchDB. This means zero new
 * dependencies for your application.
 *
 * This is also the original socket code that was used in Sag.
 *
 * @version 0.6.0
 * @package HTTP
 */
class SagNativeHTTPAdapter extends SagHTTPAdapter {
  private $connPool = array();          //Connection pool

  /**
   * Closes any sockets that are left open in the connection pool.
   */
  public function __destruct()
  {
    foreach($this->connPool as $sock)
      @fclose($sock);
  }

  public function procPacket($method, $url, $data = null, $headers = array()) {
    $buff = "$method $url HTTP/1.0\r\n";

    foreach($headers as $k => $v)
      $buff .= "$k: $v\r\n";

    $buff .= "\r\n$data"; //it's okay if $data isn't set

    if($data && $method !== "PUT")
      $buff .= "\r\n\r\n";

    // Open the socket only once we know everything is ready and valid.
    $sock = null;

    while(!$sock)
    {
      if(sizeof($this->connPool) > 0)
      {
        $maybeSock = array_shift($this->connPool);
        $meta = stream_get_meta_data($maybeSock);

        if(!$meta['timed_out'] && !$meta['eof'])
          $sock = $maybeSock;
        elseif(is_resource($maybeSock))
          fclose($maybeSock);
      }
      else
      {
        try
        {
          //these calls should throw on error
          if($this->socketOpenTimeout)
            $sock = fsockopen($this->host, $this->port, $sockErrNo, $sockErrStr, $this->socketOpenTimeout);
          else
            $sock = fsockopen($this->host, $this->port, $sockErrNo, $sockErrStr);

          //some PHP configurations don't throw when fsockopen() fails
          if(!$sock) {
            throw Exception($sockErrStr, $sockErrNo);
          }
        }
        catch(Exception $e)
        {
          throw new SagException('Was unable to fsockopen() a new socket: '.$e->getMessage());
        }
      }
    }

    if(!$sock)
      throw new SagException("Error connecting to {$this->host}:{$this->port} - $sockErrStr ($sockErrNo).");

    // Send the packet.
    fwrite($sock, $buff);

    // Set the timeout.
    if(isset($this->socketRWTimeoutSeconds))
      stream_set_timeout($sock, $this->socketRWTimeoutSeconds, $this->socketRWTimeoutMicroseconds);

    // Prepare the data structure to store the response.
    $response = new StdClass();
    $response->headers = new StdClass();
    $response->headers->_HTTP = new StdClass();
    $response->body = '';

    $isHeader = true;
    $sockInfo = stream_get_meta_data($sock);

    // Read in the response.
    while(
      !feof($sock) &&
      ( 
        $isHeader ||
        (
          !$isHeader &&
          $method != 'HEAD' &&
          (
            !isset($response->headers->{'Content-Length'}) ||
            (
              isset($response->headers->{'Content-Length'}) &&
              strlen($response->body) < $response->headers->{'Content-Length'}
            )
          )
        )
      )
    )
    {
      if($sockInfo['timed_out'])
        throw new SagException('Connection timed out while reading.');

      //TODO deal with fgets() returning false
      //TODO add tests to check binary safeness
      $line = fgets($sock);

      if($isHeader)
      {
        $line = trim($line);

        if($isHeader && empty($line))
          $isHeader = false; //the delim blank line
        else
        {
          if(!isset($response->headers->_HTTP->raw))
          {
            //the first header line is always the HTTP info
            $response->headers->_HTTP->raw = $line;

            if(preg_match('(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $line, $match))
            {
              $response->headers->_HTTP->version = $match['version'];
              $response->headers->_HTTP->status = $match['status'];
              $response->status = $match['status'];
            }
            else
              throw new SagException('There was a problem while handling the HTTP protocol.'); //whoops!
          }
          else
          {
            $line = explode(':', $line, 2);
            $response->headers->$line[0] = ltrim($line[1]);

            if($line[0] == 'Set-Cookie')
            {
              $response->cookies = new StdClass();

              foreach(explode('; ', $line[1]) as $cookie)
              {
                $crumbs = explode('=', $cookie);
                $response->cookies->{trim($crumbs[0])} = trim($crumbs[1]);
              }
            }
          }
        }
      }
      else
        $response->body .= $line;
    }

    //We're done with the socket, so someone else can use it.
    if($response->headers->Connection == 'Keep-Alive')
      $this->connPool[] = $sock;

    //Make sure we got the complete response.
    if($method != 'HEAD' && isset($response->headers->{'Content-Length'}) && strlen($response->body) < $response->headers->{'Content-Length'})
      throw new SagException('Unexpected end of packet.');

    return self::makeResult($response);
  }
}
?>
