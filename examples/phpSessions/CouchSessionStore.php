<?php
require_once '../../src/Sag.php';
require_once '../../src/SagMemoryCache.php';

class CouchSessionStore
{
  private static $sag;
  private static $sessionName;

  public static function setSag($sag)
  {
    if($sag == null)
    {
      //Use defaults
      self::$sag = new Sag('sbisbee.com');
      self::$sag->setCache(new SagMemoryCache());
    }
    elseif(!($sag instanceof Sag))
      throw new Exception('That is not an instance of Sag.');
    else
      self::$sag = $sag;

    self::$sag->setDatabase(self::$sessionName);

    return self::$sag;
  }

  public static function open($sessionPath, $sessionName)
  {
    self::$sessionName = $sessionName;

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

    //See if the design doc exists, creating it if it isn't
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

    $ddoc = new StdClass();
    $ddoc->_id = '_design/app';
    $ddoc->views = new StdClass();
    $ddoc->views->byCreateTime = new StdClass();
    $ddoc->views->byCreateTime->map = "function(doc) { emit(doc.createdAt, null); }";

    try
    {
      self::$sag->put('_design/app', $ddoc);
    }
    catch(Exception $e)
    {
      /*
       * 409 code means there was a conflict, so another client already created
       * the design doc for us. This is fine.
       */
      if($e->getCode() != 409)
        return false;
    }

    return true;
  }

  public static function close()
  {
    return true;
  }

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

      $doc = new StdClass();
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

  public static function gc($maxLife)
  {
    $now = time();

    try
    {
      $rows = self::$sag->get('/_design/app/_view/byCreateTime?include_docs=true&endkey='.$now)->body->rows;

      foreach($rows as $row)
        if($row->doc->createdOn + $maxLife < $now)
          self::$sag->delete($row->doc->_id, $row->doc->_rev);          
    }
    catch(Exception $e)
    {
      return false;
    }

    return true;
  }
}

session_set_save_handler(
                          "CouchSessionStore::open",
                          "CouchSessionStore::close",
                          "CouchSessionStore::read",
                          "CouchSessionStore::write",
                          "CouchSessionStore::destroy",
                          "CouchSessionStore::gc"
                        );
?>
