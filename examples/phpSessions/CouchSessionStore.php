<?php
require_once '../../src/Sag.php';
require_once '../../src/SagMemoryCache.php';

/**
 * Acts as an adapter between PHP's session CRUD functions and CouchDB's,
 * storing session data and meta-data on your couch.
 */
class CouchSessionStore
{
  private static $sag;
  private static $sessionName;

  /**
   * Allows users to define their own Sag object. Don't bother specifying a
   * database name though, because it will be overwritten with the results of
   * session_name().
   *
   * @param Sag $sag Your initialized Sag object.
   * @return Sag The updated Sag object that will be used.
   */
  public static function setSag($sag)
  {
    if($sag == null)
    {
      //Use defaults
      self::$sag = new Sag();
      self::$sag->setCache(new SagMemoryCache());
    }
    elseif(!($sag instanceof Sag))
      throw new Exception('That is not an instance of Sag.');
    else
      self::$sag = $sag;

    self::$sag->setDatabase(self::$sessionName, true);

    return self::$sag;
  }

  /**
   * Opens the session, creating the design document if necessary. You do not
   * need to call this function directly, because PHP will do it for you.
   *
   * @param string $sessionPath The save path (not used).
   * @param string $sessionName The session name, which will be used for the
   * database name.
   * @return bool Whether or not the operation was successful.
   */
  public static function open($sessionPath, $sessionName)
  {
    self::$sessionName = strtolower($sessionName);

    //Set up Sag
    try
    {
      if(!self::$sag)
        self::setSag(null);
    }
    catch(Exception $e)
    {
      return false;
    }

    self::$sag->setDatabase(self::$sessionName, true);

    //See if the design doc exists, creating it if it doesn't
    try
    { 
      //it does exist, so finish early
      if(self::$sag->head('_design/app')->headers->_HTTP->status != "404")
        return true;
    }
    catch(Exception $e)
    {
      //database issue
      return false;
    }

    $ddoc = new stdClass();
    $ddoc->_id = '_design/app';
    $ddoc->views = new stdClass();
    $ddoc->views->byCreateTime = new stdClass();
    $ddoc->views->byCreateTime->map = "function(doc) { emit(doc.createdAt, null); }";

    try
    {
      self::$sag->put('_design/app', $ddoc);
    }
    catch(Exception $e)
    {
      /*
       * A 409 status code means there was a conflict, so another client
       * already created the design doc for us. This is fine.
       */
      if($e->getCode() != 409)
        return false;
    }

    return true;
  }

  /**
   * Closes the session, destroying the Sag object and session name.
   *
   * @return bool Always returns true, because there's nothing for us to clean
   * up.
   */
  public static function close()
  {
    self::$sag = null;
    self::$sessionName = null;

    return true;
  }

  /**
   * Retrieves the session by its ID (document's _id).
   *
   * @param string $id The session ID.
   * @return string The serialized session data (PHP takes care of
   * deserialization for us).
   */
  public static function read($id)
  {
    try
    {
      return self::$sag->get($id)->body->data;
    }
    catch(Exception $e)
    {
      return '';
    }
  }

  /**
   * Updates the session data, creating it if necessary. This will also advance
   * the session's createdAt timestamp to time(), pushing out when it will
   * expire and be garbage collected.
   *
   * @param string $id The sesion ID.
   * @param string $data The serialized data to store.
   * @return bool Whether or not the operation was successful.
   */
  public static function write($id, $data)
  {
    try
    {
      //not necessarily expensive because we're caching
      $doc = self::$sag->get($id)->body;
    }
    catch(Exception $e)
    {
      if($e->getCode() != 404)
        return false;

      $doc = new stdClass();
      $doc->_id = $id;
    }

    $doc->data = $data;
    $doc->createdAt = time();

    try
    {
      self::$sag->put($id, $doc);
    }
    catch(Exception $e)
    {
      return false;
    }

    return true;
  }

  /**
   * Destroys the session, deleting it from CouchDB.
   *
   * @param string $id The session ID.
   * @return bool Whether or not the operation was successful.
   */
  public static function destroy($id)
  {
    try
    {
      self::$sag->delete($id);
    }
    catch(Exception $e)
    {
      return false;
    }

    return true;
  }

  /**
   * Runs garbage collection against the sessions, deleting all those that are
   * older than the number of seconds passed to this function. Uses CouchDB's
   * Bulk Document API instead of deleting each one individually.
   *
   * @param int $maxLife The maximum life of a session in seconds.
   * @return bool Whether or not the operation was successful.
   */
  public static function gc($maxLife)
  {
    $toDelete = array();
    $now = time();

    try
    {
      $rows = self::$sag->get('/_design/app/_view/byCreateTime?include_docs=true&endkey='.$now)->body->rows;

      foreach($rows as $row)
      {
        if($row->doc->createdAt + $maxLife < $now)
        {
          $row->doc->_deleted = true;
          $toDelete[] = $row->doc;
        }
      }

      if(sizeof($toDelete) > 0)
        self::$sag->bulk($toDelete);
    }
    catch(Exception $e)
    {
      return false;
    }

    return true;
  }
}

session_set_save_handler(                                                                                            
                          array(CouchSessionStore, "open"),
                          array(CouchSessionStore, "close"),
                          array(CouchSessionStore, "read"),
                          array(CouchSessionStore, "write"),
                          array(CouchSessionStore, "destroy"),
                          array(CouchSessionStore, "gc")
                        );
?>
