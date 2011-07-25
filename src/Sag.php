<?php
/*
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
require_once('SagConfigurationCheck.php');

/**
 * The Sag class provides the core functionality for talking to CouchDB.
 *
 * @version 0.5.1
 * @package Core
 */
class Sag
{
  /**
   * @var string Used by login() to use HTTP Basic Authentication.
   * @static
   */
  public static $AUTH_BASIC = "AUTH_BASIC";

  /**
   * @var string Used by login() to use HTTP Cookie Authentication.
   * @static
   */
  public static $AUTH_COOKIE = "AUTH_COOKIE";

  private $db;                          //Database name to hit.
  private $host;                        //IP or address to connect to.
  private $port;                        //Port to connect to.

  private $user;                        //Username to auth with.
  private $pass;                        //Password to auth with.
  private $authType;                    //One of the Sag::$AUTH_* variables
  private $authSession;                 //AuthSession cookie value from/for CouchDB

  private $decodeResp = true;           //Are we decoding CouchDB's JSON?

  private $socketOpenTimeout;           //The seconds until socket connection timeout
  private $socketRWTimeoutSeconds;      //The seconds for socket I/O timeout
  private $socketRWTimeoutMicroseconds; //The microseconds for socket I/O timeout

  private $cache;

  private $staleDefault;                //Whether or not to use ?stale=ok on all design doc calls

  private $connPool = array();          //Connection pool

  /**
   * @param string $host The host's IP or address of the Couch we're connecting
   * to.
   * @param string $port The host's port that Couch is listening on.
   */
  public function __construct($host = "127.0.0.1", $port = "5984")
  {
    SagConfigurationCheck::run();

    $this->host = $host;
    $this->port = $port;
  }

  /**
   * Closes any sockets that are left open in the connection pool.
   */
  public function __destruct()
  {
    foreach($this->connPool as $sock)
      @fclose($sock);
  }

  /**
   * Updates the login credentials in Sag that will be used for all further
   * communications. Pass null to both $user and $pass to turn off
   * authentication, as Sag does support blank usernames and passwords - only
   * one of them has to be set for packets to be sent with authentication.
   *
   * Cookie authentication will cause a call to the server to establish the
   * session, and will throw an exception if the credentials weren't valid.
   *
   * @param string $user The username you want to login with. (null for none)
   * @param string $pass The password you want to login with. (null for none)
   * @param string $type The type of login system being used. Defaults to
   * Sag::$AUTH_BASIC.
   *
   * @return mixed Returns true if the input was valid. If using $AUTH_COOKIE,
   * then the autoSession value will be returned. Throws on failure.
   *
   * @see $AUTH_BASIC
   * @see $AUTH_COOKIE
   */
  public function login($user, $pass, $type = null)
  {
    if($type == null)
      $type = Sag::$AUTH_BASIC;

    $this->authType = $type;

    switch($type)
    {
      case Sag::$AUTH_BASIC:
        //these will end up in a header, so don't URL encode them
        $this->user = $user;
        $this->pass = $pass;

        return true;
        break;

      case Sag::$AUTH_COOKIE:
        $user = urlencode($user);
        $pass = urlencode($pass);

        $res = $this->procPacket('POST', '/_session', "name=$user&password=$pass", array('Content-Type' => 'application/x-www-form-urlencoded'));
        $this->authSession = $res->cookies->AuthSession;
        return $this->authSession;
        break;
    }

    throw new SagException("Unknown auth type for login().");
  }

  /**
   * Sets whether Sag will decode CouchDB's JSON responses with json_decode()
   * or to simply return the JSON as a string. Defaults to true.
   *
   * @param bool $decode True to decode, false to not decode.
   * @return Sag Returns $this.
   */
  public function decode($decode)
  {
    if(!is_bool($decode))
      throw new SagException('decode() expected a boolean');

    $this->decodeResp = $decode;

    return $this;
  }

  /**
   * Performs an HTTP GET operation for the supplied URL. The database name you
   * provided is automatically prepended to the URL, so you only need to give
   * the portion of the URL that comes after the database name.
   *
   * You are responsible for URL encoding your own parameters.
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

    if($this->staleDefault)
      $url = self::setURLParameter($url, 'stale', 'ok');

    //Deal with cached items
    if($this->cache)
    {
      $prevResponse = $this->cache->get($url);

      if($prevResponse)
      {
        $response = $this->procPacket('GET', $url, null, array('If-None-Match' => $prevResponse->headers->Etag));

        if($response->headers->_HTTP->status == 304)
          return $prevResponse; //cache hit
      
        $this->cache->remove($url); 
      }

      unset($prevResponse);
    }

    //Not caching, or we are caching but there's nothing cached yet, or our
    //cached item is no longer good.
    if(!$response)
      $response = $this->procPacket('GET', $url);

    if($this->cache)
      $this->cache->set($url, $response);

    return $response;
  }

  /**
   * Performs an HTTP HEAD operation for the supplied document. This operation
   * does not try to read from a provided cache, and does not cache its
   * results.
   *
   * @see http://wiki.apache.org/couchdb/HTTP_Document_API#HEAD
   *
   * @param string $url The URL, with or without the leading slash.
   * @return mixed
   */
  public function head($url)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    //The first char of the URL should be a slash.
    if(strpos($url, '/') !== 0)
      $url = "/$url";

    if($this->staleDefault)
      $url = self::setURLParameter($url, 'stale', 'ok');

    //we're only asking for the HEAD so no caching is needed
    return $this->procPacket('HEAD', "/{$this->db}$url");
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

    $url = "/{$this->db}/$id";

    if($this->cache)
      $this->cache->remove($url);

    return $this->procPacket('DELETE', $url.'?rev='.urlencode($rev));
  }

  /**
   * PUT's the data to the document.
   *
   * @param string $id The document's _id.
   * @param mixed $data The document, which should have _id and _rev
   * properties. Can be an object, array, or string.
   *
   * @return mixed
   */
  public function put($id, $data)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!is_string($id))
      throw new SagException('put() expected a string for the doc id.');

    if(!isset($data) || (!is_object($data) && !is_string($data) && !is_array($data)))
      throw new SagException('put() needs an object for data - are you trying to use delete()?');

    $toSend = (is_string($data)) ? $data : json_encode($data);

    $url = "/{$this->db}/$id";
    $response = $this->procPacket('PUT', $url, $toSend);

    unset($toSend);

    /*
     * We're going to pretend like we issued a GET or HEAD by replacing the PUT
     * response's body with the data we sent. We then update that data with the
     * _rev from the PUT's response's body. Of course this should only run when
     * there is a successful write to the database: we don't want to be caching
     * failures.
     */
    if($this->cache && $response->body->ok) {
      if(is_string($data)) {
        $data = json_decode($data);
      }

      $data->_rev = $response->body->rev;

      $toCache = clone $response;
      $toCache->body = $data;

      $this->cache->set($url, $toCache);

      unset($toCache);
    }

    return $response;
  }


  /**
   * POST's the provided document. When using a SagCache, the created document
   * and response are not cached.
   *
   * @param mixed $data The document that you want created. Can be an object,
   * array, or string.
   * @param string $path Can be the path to a view or /all_docs. The database
   * will be prepended to the value.
   *
   * @return mixed
   */
  public function post($data, $path = null)
  {
    if(!$this->db)
      throw new SagException('No database specified');

    if(!isset($data) || (!is_string($data) && !is_object($data) && !is_array($data)))
      throw new SagException('post() needs an object for data.');

    if(!is_string($data))
      $data = json_encode($data);

    if(is_string($path) && !empty($path))
      $path = ((substr($path, 0, 1) != '/') ? '/' : '').$path;
    elseif(isset($path))
      throw new SagException('post() needs a string for a path.');

    return $this->procPacket('POST', "/{$this->db}{$path}", $data);
  }

  /**
   * Bulk pushes documents to the database.
   * 
   * This function does not leverage the caching mechanism you specify with
   * setCache().
   *
   * @param array $docs An array of the documents you want to be pushed; they
   * can be JSON strings, objects, or arrays.
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

    return $response;
  }

  /**
   * Sets which database Sag is going to send all of its database related
   * communications to (ex., dealing with documents).
   *
   * When specifying that the database should be created if it doesn't already
   * exists, this will cause an HTTP GET to be sent to /dbName and
   * createDatabase($db) if a 404 response is returned. So, only turn it on if
   * it makes sense for your application, because it could cause needless HTTP
   * GET calls.
   *
   * @param string $db The database's name, as you'd put in the URL. This
   * string will be URL encoded using PHP's urlencode().
   * @param bool $createIfNotFound Whether to try and create the specified
   * database if it doesn't exist yet (checks every time this is called).
   *
   * @return Sag Returns $this. Throws on failure.
   */
  public function setDatabase($db, $createIfNotFound = false)
  {
    if($this->db != $db)
    {
      if(!is_string($db))
        throw new SagException('setDatabase() expected a string.');

      $db = urlencode($db);

      if($createIfNotFound)
      {
        try
        {
          $result = self::procPacket('GET', "/{$db}");
        }
        catch(SagCouchException $e)
        {
          if($e->getCode() != 404)
            throw $e; //these are not the errors that we are looking for

          self::createDatabase($db);
        }
      }

      $this->db = $db;
    }

    return $this;
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

    if($incDocs !== false)
    {
      if(!is_bool($incDocs))
        throw new SagException('getAllDocs() expected a boolean for include_docs.');

      array_push($qry, "include_docs=true");
    }

    if(isset($startKey))
    {
      if(!is_string($startKey))
        throw new SagException('getAllDocs() expected a string for startkey.');

      $qry[] = 'startkey='.urlencode($startKey);
    }

    if(isset($endKey))
    {
      if(!is_string($endKey))
        throw new SagException('getAllDocs() expected a string for endkey.');

      $qry[] = 'endkey='.$endKey;
    }

    if(isset($limit))
    {
      if(!is_int($limit) || $limit < 0)
        throw new SagException('getAllDocs() expected a positive integeter for limit.');

      $qry[] = 'limit='.urlencode($limit);
    }

    $qry = '?'.implode('&', $qry);

    if(isset($keys))
    {
      if(!is_array($keys))
        throw new SagException('getAllDocs() expected an array for the keys.');

      $data = new StdClass();
      $data->keys = $keys;

      return $this->procPacket('POST', "/{$this->db}/_all_docs$qry", json_encode($data));
    }

    return $this->procPacket('GET', "/{$this->db}/_all_docs$qry");
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

    //don't need to URL encode since ints are, well, harmless lil' ol' ints
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
   * @param bool $createTarget Specifies create_target, which will create the
   * target database if it does not already exist. (optional)
   * @param string $filter The name of the filter function to use. (optional)
   * @param mixed $filterQueryParams An object or associative array of
   * parameters to be passed to the filter function via query_params. Only used
   * if $filter is set.
   *
   * @return mixed
   */
  public function replicate($src, $target, $continuous = false, $createTarget = null, $filter = null, $filterQueryParams = null)
  {
    if(empty($src) || !is_string($src))
      throw new SagException('replicate() is missing a source to replicate from.');

    if(empty($target) || !is_string($target))
      throw new SagException('replicate() is missing a target to replicate to.');

    if(!is_bool($continuous))
      throw new SagException('replicate() expected a boolean for its third argument.');

    if(isset($createTarget) && !is_bool($createTarget))
      throw new SagException('createTarget needs to be a boolean.');

    if(isset($filter))
    {
      if(!is_string($filter))
        throw new SagException('filter must be the name of a design doc\'s filter function: ddoc/filter');

      if(isset($filterQueryParams) && !is_object($filterQueryParams) && !is_array($filterQueryParams))
        throw new SagException('filterQueryParams needs to be an object or an array');
    }

    $data = new StdClass();
    $data->source = $src;
    $data->target = $target;

    //These guys are optional, so only include them if non-default to save on
    //packet size.

    if($continuous)
      $data->continuous = true;

    if($createTarget)
      $data->create_target = true;

    if($filter)
    {
      $data->filter = $filter;

      if($filterQueryParams)
        $data->filterQueryParams = $filterQueryParams;
    }

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
   * Create or update attachments on documents by passing in a serialized
   * version of your attachment (a string).
   *
   * @param string $name The attachment's name.
   * @param string $data The attachment's data, in string representation. Ie.,
   * you need to serialize your attachment.
   * @param string $contentType The proper Content-Type for your attachment.
   * @param string $docID The _id of the document that the attachment
   * belongs to.
   * @param string $rev optional The _rev of the document that the attachment
   * belongs to. Leave blank if you are creating a new document.
   *
   * @return mixed
   */
  public function setAttachment($name, $data, $contentType, $docID, $rev = null)
  {
    if(empty($docID))
      throw new SagException('You need to provide a document ID.');

    if(empty($name))
      throw new SagException('You need to provide the attachment\'s name.');

    if(empty($data))
      throw new SagException('You need to provide the attachment\'s data.');

    //TODO support type conversion, streams, etc.
    if(!is_string($data))
      throw new SagException('You need to provide the attachment\'s data as a string.');

    if(empty($contentType))
      throw new SagException('You need to provide the data\'s Content-Type.');

    return $this->procPacket('PUT', "/{$this->db}/{$docID}/{$name}".(($rev) ? "?rev=".urlencode($rev) : ""), $data, array("Content-Type" => $contentType));
  }

  /**
   * Sets the connection timeout on the socket. See setOpenTimeout() for
   * settings the read/write timeout.
   *
   * @param int $seconds
   * @return Sag Returns $this.
   */
  public function setOpenTimeout($seconds)
  {
    if(!is_int($seconds) || $seconds < 1)
      throw new Exception('setOpenTimeout() expects a positive integer.');

    $this->socketOpenTimeout = $seconds;

    return $this;
  }

  /**
   * Sets the read/write timeout period on the socket to the sum of seconds and
   * microseconds. If not set, then the default_socket_timeout setting is used
   * from your php.ini config.
   *
   * Use setOpenTimeout() to set the timeout on opening the socket.
   *
   * @param int $seconds The seconds part of the timeout.
   * @param int $microseconds optional The microseconds part of the timeout.
   * @return Sag Returns $this.
   */
  public function setRWTimeout($seconds, $microseconds = 0)
  {
    if(!is_int($microseconds) || $microseconds < 0)
      throw new SagException('setRWTimeout() expects $microseconds to be an integer >= 0.');

    //$seconds can be 0 if $microseconds > 0
    if(
      !is_int($seconds) ||
      (
        (!$microseconds && $seconds < 1) ||
        ($microseconds && $seconds < 0)
      )
    )
      throw new SagException('setRWTimeout() expects $seconds to be a positive integer.');

    $this->socketRWTimeoutSeconds = $seconds;
    $this->socketRWTimeoutMicroseconds = $microseconds;

    return $this;
  }

  /*
   * Pass an implementation of the SagCache, such as SagFileCache, that will be
   * used when retrieving objects. It is taken and stored as a reference. 
   *
   * @param SagCache An implementation of SagCache (ex., SagFileCache).
   * @return Sag Returns $this.
   */
  public function setCache(&$cacheImpl)
  {
    if(!($cacheImpl instanceof SagCache))
      throw new SagException('That is not a valid cache.');

    $this->cache = $cacheImpl;

    return $this;
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

  /**
   * Retrieves the run time metrics from CouchDB that lives at /_stats.
   *
   * @return StdClass
   */
  public function getStats()
  {
    return $this->procPacket('GET', '/_stats');
  }

  /**
   * Set whether or not to include ?stale=ok by default when running GET and
   * HEAD requests.
   *
   * When set to true, a very slight overhead in the get() and head() functions
   * will occur, as they will parse out the parameters from the URL you
   * provide and ensure that no other value is being passed to the stale
   * variable.
   *
   * @param bool $stale True will make stale=ok be sent by default.
   * @return Sag Returns $this.
   */
  public function setStaleDefault($stale)
  {
    if(!is_bool($stale))
      throw new SagException('setStaleDefault() expected a boolean argument.');

    $this->staleDefault = $stale;

    return $this;
  }

  // The main driver - does all the socket and protocol work.
  private function procPacket($method, $url, $data = null, $headers = array())
  {
    // For now we only data data as strings. Streams and other formats will be
    // permitted later.
    if($data && !is_string($data))
      throw new SagException('Unexpected data format. Please report this bug.');

    // Do some string replacing for HTTP sanity.
    $url = str_replace(array(" ", "\""), array('%20', '%22'), $url);

    // Build the request packet.
    $headers["Host"] = "{$this->host}:{$this->port}";
    $headers["User-Agent"] = "Sag/0.5.1";
    $headers["Connection"] = "Keep-Alive";

    //usernames and passwords can be blank
    if($this->authType == Sag::$AUTH_BASIC && (isset($this->user) || isset($this->pass)))
      $headers["Authorization"] = 'Basic '.base64_encode("{$this->user}:{$this->pass}");
    elseif($this->authType == Sag::$AUTH_COOKIE && isset($this->authSession))
    {
      $headers['Cookie'] = 'AuthSession='.$this->authSession;
      $headers['X-CouchDB-WWW-Authenticate'] = 'Cookie';
    }

    // JSON is our default and most used Content-Type, but others need to be
    // specified to allow attachments.
    if(!isset($headers['Content-Type']))
      $headers['Content-Type'] = 'application/json';

    if($data)
      $headers['Content-Length'] = strlen($data); 

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

  /**
   * Takes a URL and k/v combo for a URL parameter, break the query string out
   * of the URL, and sets the parameter to the k/v pair you pass in. This will
   * overwrite a paramter's value if it already exists in the URL, or simply
   * create it if it doesn't already.
   *
   *
   * @param string $url The URL to run against.
   * @param string $key The name of the parameter to set in the URL.
   * @param string $value The value of the parameter to set in the URL.
   *
   * @return string The modified URL.
   */
  private function setURLParameter($url, $key, $value)
  {
    $url = parse_url($url);
    
    parse_str($url['query'], $params);
    $params[$key] = $value;

    return $url = $url['path'].'?'.http_build_query($params);
  }
}
