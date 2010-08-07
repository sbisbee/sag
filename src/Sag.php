<?php
/*
  Copyright 2010 Sam Bisbee 

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

require_once('SagException.php');
require_once('SagCouchException.php');

/**
 * The Sag class provides the core functionality for talking to CouchDB. 
 *
 * @version 0.2.0
 * @package Core
 */
class Sag
{
  /**
   * @var string Used by login() to use HTTP Basic Authentication.
   * @static
   */
  public static $AUTH_BASIC = "AUTH_BASIC";

  private $db;                  //Database name to hit.
  private $host;                //IP or address to connect to.
  private $port;                //Port to connect to.

  private $user;                //Username to auth with.
  private $pass;                //Password to auth with.
  private $authType;            //One of the Sag::$AUTH_* variables

  private $decodeResp = true;   //Are we decoding CouchDB's JSON?

  private $cache;

  /**
   * @param string $host The host's IP or address of the Couch we're connecting
   * to.
   * @param string $port The host's port that Couch is listening on.
   */
  public function Sag($host = "127.0.0.1", $port = "5984")
  {
    $this->host = $host;
    $this->port = $port;
  }

  /**
   * Updates the login credentials in Sag that will be used for all further
   * communications. Pass null to both $user and $pass to turn off
   * authentication, as Sag does support blank usernames and passwords - only
   * one of them has to be set for packets to be sent with authentication.
   *
   * @param string $user The username you want to login with. (null for none)
   * @param string $pass The password you want to login with. (null for none)
   * @param string $type The type of login system being used. Currently only
   * accepts Sag::$AUTH_BASIC.
   *
   * @see $AUTH_BASIC
   */
  public function login($user, $pass, $type = null)
  {
    if(!isset($type))
      $type = Sag::$AUTH_BASIC;

    if($type != Sag::$AUTH_BASIC)
      throw new SagException("Unknown auth type for login()");

    $this->user = $user;
    $this->pass = $pass;
    $this->authType = $type;
  }

  /**
   * Sets whether Sag will decode CouchDB's JSON responses with json_decode()
   * or to simply return the JSON as a string. Defaults to true.
   *
   * @param bool $decode True to decode, false to not decode.
   */
  public function decode($decode)
  {
    if(!is_bool($decode))
      throw new SagException('decode() expected a boolean');

    $this->decodeResp = $decode;
  }

  /**
   * Performs an HTTP GET operation for the supplied URL. The database name you
   * provided is automatically prepended to the URL, so you only need to give
   * the portion of the URL that comes after the database name.
   *
   * @param string $url The URL, with or without the leading slash.
   * @return mixed
   */
  public function get($url)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    //The first char of the URL should be a slash.
    if(strpos($url, '/') !== 0)
      $url = "/$url";

    $url = "/{$this->db}$url";

    //Deal with cached items
    if($this->cache && ($prevResponse = $this->cache->get($url)))
    {
      $response = $this->procPacket('GET', $url, null, array('If-None-Match' => $prevResponse->headers->Etag));

      if($response->headers->_HTTP->status == 304)
        return $prevResponse; //cache hit
    }

    //Not caching, or are caching but there's nothing cached yet
    if(!$response)
      $response = $this->procPacket('GET', $url);

    if($this->cache)
      $this->cache->set($url, $response);

    return $response;
  }

  /**
   * DELETE's the specified document.
   *
   * @param string $id The document's _id.
   * @param string $rev The document's _rev. 
   *
   * @return mixed
   */
  public function delete($id, $rev)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!is_string($id) || !is_string($rev) || empty($id) || empty($rev))
      throw new SagException('delete() expects two strings.');

    if($this->cache)
      $this->cache->remove("/{$this->db}/{$id}");

    return $this->procPacket('DELETE', "/{$this->db}/$id?rev=$rev");
  }

  /**
   * PUT's the data to the document.
   *
   * @param string $id The document's _id.
   * @param object $data The document, which should have _id and _rev
   * properties.
   *
   * @return mixed
   */
  public function put($id, $data)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!is_string($id))
      throw new SagException('put() expected a string for the doc id.');

    if(!isset($data) || !is_object($data))
      throw new SagException('put() needs an object for data - are you trying to use delete()?');

    $url = "/{$this->db}/$id";
    $response = $this->procPacket('PUT', $url, json_encode($data)); 

    if($this->cache)
      $this->cache->set($url, json_encode($data));

    return $response;
  }


  /**
   * POST's the provided document.
   *
   * @param object $data The document that you want created.
   *
   * @return mixed
   */
  public function post($data)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!isset($data) || !is_object($data))
      throw new SagException('post() needs an object for data.');

    return $this->procPacket('POST', "/{$this->db}", json_encode($data)); 
  }

  /**
   * Bulk pushes documents to the database.
   *
   * @param array $docs An array of objects, which are the documents you want
   * to push.
   * @param bool $allOrNothing Whether to treat the transactions as "all or
   * nothing" or not. Defaults to false.
   * 
   * @return mixed
   */
  public function bulk($docs, $allOrNothing = false)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!is_array($docs))
      throw new SagException('bulk() expects an array for its first argument');

    if(!is_bool($allOrNothing))
      throw new SagException('bulk() expects a boolean for its second argument');

    $data = new StdClass();
    //Only send all_or_nothing if it's non-default (true), saving bandwidth.
    if($allOrNothing)
      $data->all_or_nothing = $allOrNothing;

    $data->docs = $docs;

    return $this->procPacket("POST", "/{$this->db}/_bulk_docs", json_encode($data));
  }

  /**
   * COPY's the document.
   *
   * If you are using a SagCache and are copying to an existing destination,
   * then the result will be cached (ie., what's copied to the /$destID URL).
   *
   * @param string The _id of the document you're copying.
   * @param string The _id of the document you're copying to.
   * @param string The _rev of the document you're copying to. Defaults to
   * null.
   * 
   * @return mixed
   */
  public function copy($srcID, $dstID, $dstRev = null)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(empty($srcID) || !is_string($srcID))
      throw new SagException('copy() got an invalid source ID');

    if(empty($dstID) || !is_string($dstID))
      throw new SagException('copy() got an invalid destination ID');

    if($dstRev != null && (empty($dstRev) || !is_string($dstRev)))
      throw new SagException('copy() got an invalid source revision');

    $headers = array(
      "Destination" => "$dstID".(($dstRev) ? "?rev=$dstRev" : "")
    );

    $response = $this->procPacket('COPY', "/{$this->db}/$srcID", null, $headers); 

    if($this->cache && $response->headers->Etag)
      $this->cache->set("/$dstID", $response);

    return $response;
  }

  /**
   * Sets which database Sag is going to send all of its database related
   * communications to (ex., dealing with documents).
   * 
   * @param string $db The database's name, as you'd put in the URL.
   */
  public function setDatabase($db)
  {
    if(!is_string($db))
      throw new SagException('setDatabase() expected a string.');

    $this->db = $db;
  }

  /**
   * Gets all the documents in the database with _all_docs. Its results will
   * not be cached by SagCache.
   *
   * @param bool $incDocs Whether to include the documents or not. Defaults to
   * false.
   * @param int $limit Limits the number of documents to return. Must be >= 0,
   * or null for no limit. Defaults to null (no limit).
   * @param string $startKey The startkey variable (valid JSON). Defaults to
   * null.
   * @param string $endKey The endkey variable (valid JSON). Defaults to null.
   * @param array $keys An array of keys (strings) of the specific documents
   * you're trying to get.
   * 
   * @return mixed
   */
  public function getAllDocs($incDocs = false, $limit = null, $startKey = null, $endKey = null, $keys = null)
  {
    if(!$this->db)
      throw new SagException('No database specified.');

    $qry = array();

    if(isset($incDocs))
    {
      if(!is_bool($incDocs))
        throw new SagException('getAllDocs() expected a boolean for include_docs.');

      array_push($qry, "include_docs=true");
    }       

    if(isset($startKey))
    {
      if(!is_string($startKey))
        throw new SagException('getAllDocs() expected a string for startkey.');

      array_push($qry, "startkey=$startKey");
    }

    if(isset($endKey))
    {
      if(!is_string($endKey))
        throw new SagException('getAllDocs() expected a string for endkey.');

      array_push($qry, "endkey=$endKey");
    }

    if(isset($limit))
    {
      if(!is_int($limit) || $limit < 0)
        throw new SagException('getAllDocs() expected a positive integeter for limit.');

      array_push($qry, "limit=$limit");
    }
  
    $qry = implode('&', $qry);

    if(isset($keys))
    {
      if(!is_array($keys))
        throw new SagException('gallAllDocs() expected an array for the keys.');

      $data = new StdClass();
      $data->keys = $keys;

      return $this->procPacket('POST', "/{$this->db}/_all_docs?$qry", json_encode($data));
    }

    return $this->procPacket('GET', "/{$this->db}/_all_docs?$qry");
  }

  /**
   * Gets all the databases on the server with _all_dbs.
   *
   * @return mixed
   */
  public function getAllDatabases()
  {
    return $this->procPacket('GET', '/_all_dbs');
  }

  /**
   * Uses CouchDB to generate IDs.
   * 
   * @param int $num The number of IDs to generate (>= 0). Defaults to 10.
   * @returns mixed
   */
  public function generateIDs($num = 10)
  {
    if(!is_int($num) || $num < 0)
      throw new SagException('generateIDs() expected an integer >= 0.');

    return $this->procPacket('GET', "/_uuids?count=$num");
  }

  /**
   * Creates a database with the specified name.
   *
   * @param string $name The name of the database you want to create.
   *
   * @return mixed
   */
  public function createDatabase($name)
  {
    if(empty($name) || !is_string($name))
      throw new SagException('createDatabase() expected a valid database name');

    return $this->procPacket('PUT', "/$name"); 
  }

  /**
   * Deletes the specified database.
   *
   * @param string $name The database's name.
   *
   * @return mixed
   */ 
  public function deleteDatabase($name)
  {
    if(empty($name) || !is_string($name))
      throw new SagException('deleteDatabase() expected a valid database name');

    return $this->procPacket('DELETE', "/$name");
  }

  /**
   * Starts a replication job between two databases, independently of which
   * database you set with Sag. 
   * 
   * @param string $src The name of the database that you are replicating from.
   * @param string $target The name of the database that you are replicating
   * to. 
   * @param bool $continuous Whether to make this a continuous replication job
   * or not. Defaults to false.
   * 
   * @return mixed
   */
  public function replicate($src, $target, $continuous = false)
  {
    if(empty($src) || !is_string($src))
      throw new SagException('replicate() is missing a source to replicate from.');

    if(empty($target) || !is_string($target))
      throw new SagException('replicate() is missing a target to replicate to.');

    if(!is_bool($continuous))
      throw new SagException('replicate() expected a boolean for its third argument.');

    $data = new StdClass();
    $data->source = $src;
    $data->target = $target;

    if($continuous)
      $data->continuous = true; //only include if true (non-default), decreasing packet size

    return $this->procPacket('POST', '/_replicate', json_encode($data));
  }

  /**
   * Starts a compaction job on the database you selected, or optionally one of
   * its views.
   *
   * @param string $viewName The database's view that you want to compact,
   * instead of the whole database.
   * 
   * @return mixed
   */
  public function compact($viewName = null)
  {
    return $this->procPacket('POST', "/{$this->db}/_compact".((empty($viewName)) ? '' : "/$viewName"));
  }

  /**
   * Pass an implementation of the SagCache, such as SagFileCache, that will be
   * used when retrieving objects. It is taken and stored as a reference. 
   *
   * @param SagCache An implementation of SagCache (ex., SagFileCache).
   */
  public function setCache(&$cacheImpl)
  {
    if(!($cacheImpl instanceof SagCache))
      throw new SagException('That is not a valid cache.');

    $this->cache = $cacheImpl;
  }

  /**
   * Returns the cache object that's currently being used. 
   *
   * @return SagCache
   */
  public function getCache()
  {
    return $this->cache;
  }

  /**
   * Returns the name of the database Sag is currently working with, or null if
   * setDatabase() hasn't been called yet.
   *
   * @return String
   */
  public function currentDatabase()
  {
    return $this->db;
  }

  // The main driver - does all the socket and protocol work.
  private function procPacket($method, $url, $data = null, $headers = array())
  {
    // Do some string replacing for HTTP sanity.
    $url = str_replace(array(" ", "\""), array('%20', '%22'), $url);

    // Build the request packet.
    $headers["Host"] = "{$this->host}:{$this->port}";
    $headers["User-Agent"] = "Sag/.1";
    
    //usernames and passwords can be blank
    if(isset($this->user) || isset($this->pass))
    {
      switch($this->authType)
      {
        case Sag::$AUTH_BASIC:
          $headers["Authorization"] = 'Basic '.base64_encode("{$this->user}:{$this->pass}"); 
          break;

        default:
          //this should never happen with login()'s validation, but just in case
          throw new SagException('Unknown auth type.');
          break;
      }
    }

    $buff = "$method $url HTTP/1.0\r\n";
    foreach($headers as $k => $v)
      if($k != 'Host' || $k != 'User-Agent' || $k != 'Content-Length' || $k != 'Content-Type')
        $buff .= "$k: $v\r\n";

    if($data)
      $buff .= "Content-Length: ".strlen($data)."\r\n"
              ."Content-Type: application/json\r\n\r\n"
              ."$data\r\n";
    else
      $buff .= "\r\n";

    // Open the socket only once we know everything is ready and valid.
    $sock = @fsockopen($this->host, $this->port, $sockErrNo, $sockErrStr);
    if(!$sock)
      throw new SagException(
        "Error connecting to {$this->host}:{$this->port} - $sockErrStr ($sockErrNo)."
      );

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
            else
              throw new SagException('There was a problem while handling the HTTP protocol.'); //whoops!
          }
          else
          {
            $line = explode(':', $line, 2);
            $response->headers->$line[0] = trim($line[1]);
          }
        }
      }
      else
        $response->body .= $line;
    }

    $json = json_decode($response->body);
    if(!empty($json->error))
      throw new SagCouchException("{$json->error} ({$json->reason})", $response->headers->_HTTP->status);

    $response->body = ($this->decodeResp) ? $json : $response->body;

    return $response;
  }
}
?>
