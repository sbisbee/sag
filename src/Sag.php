<?php
class Sag
{
  private $db;
  private $host;
  private $port;
  private $user;
  private $pass;

  private $decodeResp = true;

  public function Sag($host = "127.0.0.1", $port = "5984", $user = null, $pass = null)
  {
    $this->host = $host;
    $this->port = $port;
    $this->user = $user;
    $this->pass = $pass; 
  }

  private static function err($msg)
  {
    return "Sag Error: $msg";
  }

  public function decode($decode)
  {
    if(!is_bool($decode))
      throw new Exception($this->err('decode() expected a boolean'));

    $this->decodeResp = $decode;
  }

  public function get($url)
  {
    if(!$this->db)
      throw new Exception($this->err('No database specified'));

    return $this->procPacket('GET', "/{$this->db}$url");
  }

  public function delete($id, $rev)
  {
    if(!$this->db)
      throw new Exception($this->err('No database specified'));

    if(!is_string($id) || !is_string($id))
      throw new Exception($this->err('delete() expects two strings.'));

    return $this->procPacket('DELETE', "/{$this->db}/$id?rev=$rev");
  }

  public function put($id, $data)
  {
    if(!$this->db)
      throw new Exception($this->err('No database specified'));

    if(!is_string($id))
      throw new Exception($this->err('put() expected a string for the doc id.'));

    if(!isset($data) || !is_object($data))
      throw new Exception($this->err('put() needs an object for data - are you trying to use delete()?'));

    return $this->procPacket('PUT', "/{$this->db}/$id", json_encode($data)); 
  }

  public function post($data)
  {
    if(!$this->db)
      throw new Exception($this->err('No database specified'));

    if(!isset($data) || !is_object($data))
      throw new Exception($this->err('post() needs an object for data.'));

    return $this->procPacket('POST', "/{$this->db}", json_encode($data)); 
  }

  public function copy($srcID, $dstID, $dstRev = null)
  {
    if(!$this->db)
      throw new Exception($this->err('No database specified'));

    if(empty($srcID) || !is_string($srcID))
      throw new Exception($this->err('copy() got an invalid source ID'));

    if(empty($dstID) || !is_string($dstID))
      throw new Exception($this->err('copy() got an invalid destination ID'));

    if($dstRev != null && (empty($dstRev) || !is_string($dstRev)))
      throw new Exception($this->err('copy() got an invalid source revision'));

    $headers = array(
      "Destination" => "$dstID".(($dstRev) ? "?rev=$dstRev" : "")
    );

    return $this->procPacket('COPY', "/{$this->db}/$srcID", null, $headers); 
  }

  public function setDatabase($db)
  {
    if(!is_string($db))
      throw new Exception($this->err('setDatabase() expected a string.'));

    $this->db = $db;
  }

  public function getAllDocs($incDocs = false, $limit = null, $startKey = null, $endKey = null)
  {
    if(!$this->db)
      throw new Exception($this->err('No database specified.'));

    $qry = array();

    if(isset($incDocs))
    {
      if(!is_bool($incDocs))
        throw new Exception($this->err('getAllDocs() expected a boolean for include_docs.'));

      array_push($qry, "include_docs=true");
    }       

    if(isset($startKey))
    {
      if(!is_string($startKey))
        throw new Exception($this->err('getAllDocs() expected a string for startkey.'));

      array_push($qry, "startkey=$startKey");
    }

    if(isset($endKey))
    {
      if(!is_string($endKey))
        throw new Exception($this->err('getAllDocs() expected a string for endkey.'));

      array_push($qry, "endkey=$endKey");
    }

    if(isset($limit))
    {
      if(!is_int($limit) || $limit < 0)
        throw new Exception($this->err('getAllDocs() expected a positive integeter for limit.'));

      array_push($qry, "limit=$limit");
    }

    $qry = implode('&', $qry);

    return $this->procPacket('GET', "/{$this->db}/_all_docs?$qry");
  }

  public function getAllDatabases()
  {
    return $this->procPacket('GET', '/_all_dbs');
  }

  public function getAllDocsBySeq($incDocs = false, $limit = null, $startKey = null, $endKey = null)
  {
    if(!$this->db)
      throw new Exception($this->err('No database specified.'));

    $qry = array();

    if(isset($incDocs))
    {
      if(!is_bool($incDocs))
        throw new Exception($this->err('getAllDocs() expected a boolean for include_docs.'));

      array_push($qry, "include_docs=true");
    }       

    if(isset($startKey))
    {
      if(!is_string($startKey))
        throw new Exception($this->err('getAllDocs() expected a string for startkey.'));

      array_push($qry, "startkey=$startKey");
    }

    if(isset($endKey))
    {
      if(!is_string($endKey))
        throw new Exception($this->err('getAllDocs() expected a string for endkey.'));

      array_push($qry, "endkey=$endKey");
    }

    if(isset($limit))
    {
      if(!is_int($limit) || $limit < 0)
        throw new Exception($this->err('getAllDocs() expected a positive integeter for limit.'));

      array_push($qry, "limit=$limit");
    }

    $qry = implode('&', $qry);

    return $this->procPacket('GET', "/{$this->db}/_all_docs_by_seq?$qry");
  }

  public function generateIDs($num = 10)
  {
    if(!is_int($num) || $num < 0)
      throw new Exception($this->err('generateIDs() expected an integer >= 0.'));

    return $this->procPacket('GET', "/_uuids?count=$num");
  }

  public function createDatabase($name)
  {
    if(empty($name) || !is_string($name))
      throw new Exception($this->err('createDatabase() expected a valid database name'));

    return $this->procPacket('PUT', "/$name"); 
  }

  public function deleteDatabase($name)
  {
    if(empty($name) || !is_string($name))
      throw new Exception($this->err('deleteDatabase() expected a valid database name'));

    return $this->procPacket('DELETE', "/$name");
  }

  private function procPacket($method, $url, $data = null, $headers = array())
  {
    // Open the socket.
    $sock = @fsockopen($this->host, $this->port, $errno);
    if(!$sock)
      throw new Exception($this->err("couldn't connect to {$this->host} on port {$this->port} ($errno)."));

    // Build the request packet.
    $buff = "$method $url HTTP/1.0\r\n"
            ."Host: {$this->host}:{$this->port}\r\n"
            ."User-Agent: Sag/.1\r\n";

    foreach($headers as $k => $v)
      if($k != 'Host' || $k != 'User-Agent' || $k != 'Content-Length' || $k != 'Content-Type')
        $buff .= "$k: $v\r\n";

    if($data)
      $buff .= "Content-Length: ".strlen($data)."\r\n"
              ."Content-Type: application/json\r\n\r\n"
              ."$data\r\n";
    else
      $buff .= "\r\n";

    // Send the packet.
    fwrite($sock, $buff);

    // Prepare the data structure to store the response.
    $response = new StdClass();
    $response->headers = new StdClass();
    $response->body = '';

    // Read in the response.
    $isHeader = true; //whether or not we're reading the HTTP headers or data

    while(!feof($sock))
    {
      $line = fgets($sock);

      if($isHeader)
      {
        $line = trim($line);

        if(empty($line))
          $isHeader = false; //the delim blank line
        else
        {
          if(!isset($response->headers->_HTTP))
          { 
            //the first header line is always the HTTP info
            $response->headers->_HTTP->raw = $line;

            if(preg_match('(^HTTP/(?P<version>\d+\.\d+)\s+(?P<status>\d+))S', $line, $match))
            {
              $response->headers->_HTTP->version = $match['version'];
              $response->headers->_HTTP->status = $match['status'];
            }
          }
          else
          {
            $line = explode(':', $line, 2);
            $response->headers->$line[0] = $line[1];
          }
        }
      }
      else
        $response->body .= $line;
    }

    $json = json_decode($response->body);
    if(!empty($json->error))
      throw new Exception("CouchDB Error: {$json->error} ({$json->reason})", $response->headers->_HTTP->status);

    $response->body = ($this->decodeResp) ? $json : $response->body;

    return $response;
  }
}
?>
