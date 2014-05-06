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

// See the README in tests/ for information on running and writing these tests.

require_once('Sag.php');
require_once('SagFileCache.php');
require_once('SagMemoryCache.php');

class SagTest extends PHPUnit_Framework_TestCase
{
  protected $couchIP;
  protected $couchDBName;
  protected $couchAdminName;
  protected $couchAdminPass;
  protected $couchHTTPAdapter;
  protected $couchSSL;

  protected $couch;
  protected $session_couch;
  protected $noCacheCouch;

  public function setUp()
  {
    $this->couchIP = ($GLOBALS['host']) ? $GLOBALS['host'] : '127.0.0.1';
    $this->couchPort = ($GLOBALS['port']) ? $GLOBALS['port'] : '5984';
    $this->couchDBName = ($GLOBALS['db']) ? $GLOBALS['db'] : 'sag_tests';
    $this->couchAdminName = ($GLOBALS['adminName']) ? $GLOBALS['adminName'] : 'admin';
    $this->couchAdminPass = ($GLOBALS['adminPass']) ? $GLOBALS['adminPass'] : 'passwd';
    $this->couchHTTPAdapter = $GLOBALS['httpAdapter'];
    $this->couchSSL = (isset($GLOBALS['ssl'])) ? $GLOBALS['ssl'] : false;

    $this->couch = new Sag($this->couchIP, $this->couchPort);
    $this->couch->setHTTPAdapter($this->couchHTTPAdapter);
    $this->couch->useSSL($this->couchSSL);
    $this->couch->login($this->couchAdminName, $this->couchAdminPass);
    $this->couch->setDatabase($this->couchDBName);
    $this->couch->setRWTimeout(5);

    $this->session_couch = new Sag($this->couchIP, $this->couchPort);
    $this->session_couch->setHTTPAdapter($this->couchHTTPAdapter);
    $this->session_couch->useSSL($this->couchSSL);
    $this->session_couch->setDatabase($this->couchDBName);
    $this->session_couch->login($this->couchAdminName, $this->couchAdminPass);

    $this->noCacheCouch = new Sag($this->couchIP, $this->couchPort);
    $this->noCacheCouch->setHTTPAdapter($this->couchHTTPAdapter);
    $this->noCacheCouch->useSSL($this->couchSSL);
    $this->noCacheCouch->setDatabase($this->couchDBName);
    $this->noCacheCouch->login($this->couchAdminName, $this->couchAdminPass);
  }

  public function test_setPathPrefix() {
    $this->assertEquals($this->couch->setPathPrefix('db'), $this->couch);
    $this->assertEquals('db', $this->couch->getPathPrefix());
    $this->assertEquals($this->couch->setPathPrefix(''), $this->couch);
  }

  public function test_currentHTTPAdapter() {
    $this->assertEquals($this->couch->currentHTTPAdapter(), $this->couchHTTPAdapter);
  }

  public function test_createDB()
  {
    $result = $this->couch->createDatabase($this->couchDBName);
    $this->assertTrue($result->body->ok);
  }

  public function test_allDatabases()
  {
    $this->assertTrue(in_array($this->couchDBName, $this->couch->getAllDatabases()->body));
  }

  public function test_newDoc()
  {
    $doc = new stdClass();
    $doc->foo = 'bar';

    $result = $this->couch->put('1', $doc);
    $this->assertTrue($result->body->ok);
    $this->assertEquals($result->body->id, '1');
  }

  public function test_newDocFromArray()
  {
    $doc = array("hi" => "there");
    $this->assertTrue($this->couch->post($doc)->body->ok);
  }

  public function test_newDocFromString()
  {
    $doc = '{"aw": "yeah", "number": 1}';
    $this->assertTrue($this->couch->post($doc)->body->ok);
  }

  public function test_newDocInvalidType()
  {
    try
    {
      $this->couch->post(123); //should throw
      $this->assertTrue(false); //shouldn't reach this line
    }
    catch(Exception $e)
    {
      $this->assertTrue(true);
    }
  }

  public function test_twoPosts() {
    $docs = array(
      array("one" => "bwah"),
      array("two" => "bwah")
    );

    $this->assertTrue($this->couch->post($docs[0])->body->ok);

    $resp = $this->couch->post($docs[1]);
    $this->assertTrue($resp->body->ok);

    /*
     * Make sure the fields didn't get appended:
     * http://uk3.php.net/manual/en/function.curl-setopt-array.php#104369
     */
    $resp = $this->couch->get($resp->body->id);
    $this->assertEquals($resp->body->two, $docs[1]['two']);
    $this->assertFalse(isset($resp->body->one));
  }

  public function test_getID()
  {
    $result = $this->couch->get('/1');
    $this->assertEquals($result->body->_id, '1');
    $this->assertEquals($result->body->foo, 'bar');

    //make sure we're prepending slashes when they're not present
    $this->assertEquals($result->body->_id, $this->couch->get('1')->body->_id);
  }

  public function test_bothStatusSet() {
    $result = $this->couch->get('/1');
    $this->assertTrue(is_string($result->headers->_HTTP->status));
    $this->assertEquals($result->status, $result->headers->_HTTP->status);
  }

  public function test_postAllDocs()
  {
    $result = $this->couch->post(array('keys' => array('1')), '/_all_docs');
    $this->assertEquals(1, sizeof($result->body->rows));
    $this->assertEquals("1", $result->body->rows[0]->id);
    
    $validData = new stdClass();
    $invalidPaths = array(array(), "", new stdClass(), false, true);

    foreach($invalidPaths as $v)
    {
      try
      {
        $this->couch->post($validData, $v);
        $this->assertTrue(false); //above should throw
      }
      catch(SagException $e)
      {
        $this->assertTrue(true);
      }
      catch(Exception $e)
      {
        $this->assertTrue(false); //wrong type of exception
      }
    }
  }

  public function test_head()
  {
    $metaDoc = $this->couch->head('/1');
    $this->assertEquals($metaDoc->headers->_HTTP->status, "200");
  }

  public function test_copyToNew()
  {
    $result = $this->couch->copy('1', '1copy');
    $this->assertEquals($result->headers->_HTTP->status, '201');
    $this->assertEquals($result->body->id, '1copy');
  }

  public function test_copyToOverwrite()
  {
    $result = $this->couch->copy('1', '1copy', $this->couch->get('/1copy')->body->_rev);
    $this->assertEquals($result->headers->_HTTP->status, '201');
  }

  public function test_tempView()
  {
    $data = new stdClass();
    $data->map = 'function(doc) { emit(doc._id, 1); }';
    $data->reduce = '_sum';

    $result = $this->couch->post($data, '/_temp_view')->body->rows[0]->value;
    $this->assertTrue(is_int($result));
    $this->assertTrue($result > 0);
  }

  public function test_queryEmptyView() {
    $ddoc = new stdClass();
    $ddoc->_id = '_design/app';
    $ddoc->language = 'javascript';
    $ddoc->views = new stdClass();
    $ddoc->views->none = new stdClass();
    $ddoc->views->none->map = 'function() { }';

    $ddocResult = $this->couch->put($ddoc->_id, $ddoc);
    $this->assertTrue($ddocResult->body->ok);

    $result = $this->couch->get('/_design/app/_view/none');

    $this->assertTrue(is_object($result->headers), 'Parsed headers');
    $this->assertTrue(is_object($result->headers->_HTTP), 'Parsed first line');
    $this->assertEquals($result->headers->_HTTP->status, 200, 'HTTP status code');
    $this->assertTrue(is_object($result->body), 'Make sure we parsed the JSON object properly');
    $this->assertTrue(is_array($result->body->rows), 'Rows is an array');
    $this->assertEquals(sizeof($result->body->rows), 0, 'Empty rows array');

    // delete design doc for future use
    $this->couch->delete('/_design/app', $ddocResult->body->rev);
  }

  public function test_getIDNoDecode()
  {
    $this->couch->decode(false);

    $json = $this->couch->get('/1')->body;
    $this->assertTrue(is_string($json));
    $this->assertEquals(json_decode($json)->_id, '1');

    $this->couch->decode(true); //for the benefit of future tests
  }

  public function test_updateDoc()
  {
    //get it and set it...
    $doc = $this->couch->get('/1')->body;
    $doc->foo = 'foo';

    //...send it...
    $this->assertTrue($this->couch->put($doc->_id, $doc)->body->ok);

    //...and get it again
    $this->assertEquals($this->couch->get('/1')->body->foo, 'foo');
  }

  public function test_getAllDocs() {
    $resDefaults = $this->couch->getAllDocs();
    $this->assertTrue(is_array($resDefaults->body->rows));
    $this->assertTrue(isset($resDefaults->body->rows[0]));
    $this->assertTrue(isset($resDefaults->body->rows[0]->value));
    $this->assertFalse(isset($resDefaults->body->rows[0]->doc));

    $resDescending = $this->couch->getAllDocs(true, null, '{}', '1', null, true, 0);
    $this->assertEquals('1', end($resDescending->body->rows)->id);

    try {
      // should throw
      $this->couch->getAllDocs(true, null, '[]', '""', null, new stdClass(), "'");
      $this->assertTrue(false);
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }

    $resAllWithDocs = $this->couch->getAllDocs(true, null, '[]', '"~~~~~~~~~~~"');
    $this->assertTrue(is_array($resAllWithDocs->body->rows));
    $this->assertTrue(isset($resAllWithDocs->body->rows[0]->value));
    $this->assertTrue(isset($resAllWithDocs->body->rows[0]->doc));
    $this->assertEquals(sizeof($resDefaults->body->rows),
      sizeof($resAllWithDocs->body->rows));

    $resLimitZero = $this->couch->getAllDocs(false, 0);
    $this->assertTrue(is_array($resLimitZero->body->rows));
    $this->assertTrue(empty($resLimitZero->body->rows)); 

    $this->assertEquals('1',
      $this->couch->getAllDocs(true, null, null, null, array("1"))->body->rows[0]->id);

    $resSkipFirst = $this->couch->getAllDocs(false, 1, null, null, null, false, 1);
    $this->assertTrue(is_array($resSkipFirst->body->rows));
    $this->assertEquals(1, $resSkipFirst->body->offset);
  }

  public function test_deleteDoc()
  {
    $doc = $this->couch->get('/1')->body;
    $this->assertTrue($this->couch->delete($doc->_id, $doc->_rev)->body->ok);

    try
    {
      $doc = $this->couch->get('/1');
      $this->assertTrue(false); //previous line should have thrown an exception
    }
    catch(SagCouchException $e)
    {
      //make sure that we're setting codes correctly and catching the right exception
      $this->assertEquals($e->getCode(), '404');
    }
  }

  public function test_genIDs()
  {
    $uuids = $this->couch->generateIDs()->body->uuids;
    $this->assertTrue(is_array($uuids));
    $this->assertEquals(sizeof($uuids), 10);
  }

  public function test_errorHandling()
  {
    try
    {
      $this->couch->generateIDs(-1); //should throw a SagException
      $this->assertTrue(false);
    }
    catch(SagException $e)
    {
      $this->assertTrue(true);
    }

    try
    {
      $this->couch->get('/_all_docs?key=badJSON'); //should throw a SagCouchException
      $this->assertTrue(false);
    }
    catch(SagCouchException $e)
    {
      $this->assertTrue(true);
    }
  }

  public function test_bulk()
  {
    $a = new stdClass();
    $a->foo = "bar";
    $a->bwah = "hi";

    $b = new stdClass();
    $b->hi = "there";
    $b->lo = "fi";

    $c = new stdClass();
    $c->_id = "namedDoc";
    $c->num = 123;

    $docs = array($a, $b, $c);

    $result = $this->couch->bulk($docs);
    $this->assertTrue(is_array($result->body));
    $this->assertEquals(sizeof($result->body), sizeof($docs));

    foreach($result->body as $i => $res)
    {
      $remoteDoc = $this->couch->get($res->id)->body;

      foreach($docs[$i] as $k => $v)
        $this->assertEquals($remoteDoc->$k, $v);
    }
  }

  public function test_replication()
  {
    $newDB = ($GLOBALS['dbReplication']) ? $GLOBALS['dbReplication'] : 'sag_tests_replication';

    // Basic
    $this->assertFalse(in_array($newDB, $this->couch->getAllDatabases()->body));
    $this->assertTrue($this->couch->createDatabase($newDB)->body->ok);
    $this->assertTrue($this->couch->replicate($this->couchDBName, $newDB)->body->ok);
    $this->assertTrue($this->couch->deleteDatabase($newDB)->body->ok);

    // create_target
    $this->assertFalse(in_array($newDB, $this->couch->getAllDatabases()->body));
    $this->assertTrue($this->couch->replicate($this->couchDBName, $newDB, false, true)->body->ok);
    $this->assertTrue(in_array($newDB, $this->couch->getAllDatabases()->body));
    $this->assertTrue($this->couch->deleteDatabase($newDB)->body->ok);

    // filter
    try
    {
      //Provide a valid filter function that does not exist.
      $this->assertTrue($this->couch->replicate($this->couchDBName, $newDB, false, true, "test")->body->ok);
      $this->assertFalse(true); //should not get this far
    }
    catch(SagCouchException $e)
    {
      $this->assertTrue(true); //we want this to happen
    }

    try
    {
      $this->assertFalse($this->couch->replicate($this->couchDBName, $newDB, false, false, 123)->body->ok);
      $this->assertFalse(true); //should not get this far
    }
    catch(SagException $e)
    {
      $this->assertTrue(true); //we want this to happen
    }

    // filter query params
    try
    {
      //Provide a valid filter function that does not exist.
      $this->assertTrue($this->couch->replicate($this->couchDBName, $newDB, false, true, "test", 123)->body->ok);
      $this->assertFalse(true); //should not get this far
    }
    catch(SagException $e)
    {
      $this->assertTrue(true); //we want this to happen
    }
  }

  public function test_compactView()
  {
    $designID = "bwah";

    $ddoc = new stdClass();
    $ddoc->_id = "_design/$designID";
    $ddoc->language = "javascript";
    $ddoc->views = new stdClass();
    $ddoc->views->all = new stdClass();
    $ddoc->views->all->map = "function(doc) { emit(null, doc); }";

    $this->assertTrue($this->couch->post($ddoc)->body->ok);
    $this->assertTrue($this->couch->compact($designID)->body->ok);
  }

  public function test_compactDatabase()
  {
    $this->assertTrue($this->couch->compact()->body->ok);
  }

  public function test_attachments()
  {
    $docID = 'howdy';
    $name = 'lyrics';
    $data = 'Somebody once told me';
    $ct = 'text/plain';

    $res = $this->couch->setAttachment($name, $data, $ct, $docID);

    // Make sure the new doc was created.
    $this->assertEquals('201', $res->headers->_HTTP->status);

    $res = $this->couch->get("/$docID");

    // Same type?
    $this->assertEquals($ct, $res->body->_attachments->{$name}->content_type);

    // Don't get the whole attachment by default.
    $this->assertTrue($res->body->_attachments->{$name}->stub);

    // Get the attachment inline style
    $res = $this->couch->get("/$docID?attachments=true");

    // Make sure we are not crazy.
    $this->assertTrue(is_object($res->body));
    $this->assertTrue(is_object($res->body->_attachments));
    $this->assertTrue(is_object($res->body->_attachments->{$name}));
    $this->assertTrue(is_string($res->body->_attachments->{$name}->data));

    // Check contents - text/plain gets base64 encoded
    $this->assertEquals($data, base64_decode($res->body->_attachments->{$name}->data));

    // Check contents, via stand alone
    $this->assertEquals($data, $this->couch->get("/$docID/$name")->body);

    // Try to update the attachment, forcing the ?rev URL param to be sent.
    $data = 'the world was gonna roll me.';
    $res = $this->couch->setAttachment($name, $data, $ct, $docID, $res->body->_rev);

    // Make sure the new doc was updated.
    $this->assertEquals('201', $res->headers->_HTTP->status);

    $res = $this->couch->get("/$docID");

    // Same type?
    $this->assertEquals($ct, $res->body->_attachments->{$name}->content_type);

    // Don't get the whole attachment by default.
    $this->assertTrue($res->body->_attachments->{$name}->stub);

    // Get the attachment inline style
    $res = $this->couch->get("/$docID?attachments=true");

    // Check contents - text/plain gets base64 encoded
    $this->assertEquals($data, base64_decode($res->body->_attachments->{$name}->data));

    // Check contents, via stand alone
    $this->assertEquals($data, $this->couch->get("/$docID/$name")->body);
  }

  public function test_createSession() {
    $resp = $this->session_couch->login($this->couchAdminName, $this->couchAdminPass, Sag::$AUTH_COOKIE);
    $this->assertTrue(is_string($resp), 'Got a string back');
  }

  public function test_getSession() {
    //we are already logged in, so give it a shot
    $session = $this->couch->getSession();

    $this->assertEquals(200, $session->headers->_HTTP->status);
    $this->assertTrue($session->body->ok);
    $this->assertEquals($this->couchAdminName, $session->body->userCtx->name);

    //logout and get the session again
    $this->couch->login(null, null);
    $session = $this->couch->getSession();

    $this->assertEquals(200, $session->headers->_HTTP->status);
    $this->assertTrue($session->body->ok);
    $this->assertEquals(null, $session->body->userCtx->name);

    //log back in
    $this->couch->login($this->couchAdminName, $this->couchAdminPass);
  }

  public function test_createDocWithSession() {
    $db = new Sag($this->couchIP, $this->couchPort);
    $db->setDatabase($this->couchDBName);
    $db->login($this->couchAdminName, $this->couchAdminPass, Sag::$AUTH_COOKIE);

    $doc = new stdClass();
    $doc->sag = 'for couchdb';

    $res = $db->put('sag', $doc);
    $this->assertTrue($res->body->ok);

    $del_res = $db->delete('sag', $res->body->rev);
    $this->assertTrue($del_res->body->ok);
  }

  public function test_setCache()
  {
    $cache = new SagFileCache('/tmp/sag');
    $this->couch->setCache($cache);
    $this->assertEquals($cache, $this->couch->getCache()); 

    try {
      // should throw
      $this->couch->setCache(new stdClass());
      $this->assertTrue(false);
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }
  }

  public function test_getFromCache()
  {
    $cache = new SagFileCache('/tmp/sag');
    $this->couch->setCache($cache);

    $doc = new stdClass();
    $doc->hi = "there";

    $id = $this->couch->post($doc)->body->id;
    
    //doc creation with POST is not cached (vs PUT)
    $cFileName = $cache->makeFilename("/{$this->couch->currentDatabase()}/$id");
    $this->assertFalse(is_file($cFileName));

    //get the file, putting it in the cache
    $fromDB = $this->couch->get("/$id");
    $this->assertEquals($fromDB->headers->_HTTP->status, 200);

    //should now be cached
    $this->assertTrue(is_file($cFileName));
    $this->assertEquals(json_encode($fromDB), file_get_contents($cFileName));

    //now create a doc with PUT, which should cache
    $doc = new stdClass();
    $doc->_id = 'bwah';
    $doc->foo = 'bar';

    $cFileName = $cache->makeFilename("/{$this->couch->currentDatabase()}/{$doc->_id}");
    $this->assertFalse(is_file($cFileName));

    $fromDB = $this->couch->put($doc->_id, $doc);

    $this->assertTrue($fromDB->body->ok);
    $this->assertEquals($fromDB->body->id, $doc->_id);

    $this->assertTrue(is_file($cFileName));

    $fromCache = json_decode(file_get_contents($cFileName));

    $this->assertEquals($fromCache->body->_id, $doc->_id);
    $this->assertEquals($fromCache->body->_rev, $fromDB->body->rev);
    $this->assertEquals($fromCache->body->foo, $doc->foo);

    /* 
     * get() using the cache, which should result in a HEAD and a 304, meaning
     * the cache returns the 201 Created response.
     */
    $fromDB = $this->couch->get($doc->_id);

    $this->assertEquals($fromDB->body->_id, $doc->_id);
    $this->assertEquals($fromDB->body->foo, $doc->foo);
    $this->assertEquals($fromDB->headers->_HTTP->status, '201');

    /*
     * Now we asynchronously update the cached document using a different Sag
     * instance so as to not invalidate the cached item. Then we'll try to get
     * the cached item with the first instance, which will result in a cache
     * miss and a GET, and should cause the old cached item to be removed.
     */
    $this->noCacheCouch->put($doc->_id, $doc);

    $fromDB = $this->couch->get($doc->_id);

    $this->assertEquals($fromDB->body->_id, $doc->_id);
    $this->assertNotEquals($fromDB->body->_rev, $doc->_rev);
    $this->assertEquals($fromDB->body->foo, $doc->foo);
    $this->assertEquals($fromDB->headers->_HTTP->status, '200');
  }

  public function test_setStaleDefault()
  {
    // Test passing valid values.
    try
    {
      //We do not want these to throw an exception

      $this->couch->setStaleDefault(true);
      $this->couch->setStaleDefault(false);
      $this->couch->setStaleDefault(false);
      $this->assertTrue(true);
    }
    catch(Exception $e)
    {
      $this->assertTrue(false);
    }

    // Test passing invalid values.
    try
    {
      //We want this to throw an exception
      $this->couch->setStaleDefault(123);
      $this->assertTrue(false);
    }
    catch(Exception $e)
    {
      $this->assertTrue(true);
    }

    // Test updating a ddoc and then querying for its old results
    $ddoc = new stdClass();
    $ddoc->_id = "_design/app";
    $ddoc->views = new stdClass();
    $ddoc->views->count = new stdClass();
    $ddoc->views->count->map = 'function(doc) { emit(null, 1); }';
    $ddoc->views->count->reduce = '_sum';

    $this->couch->put($ddoc->_id, $ddoc);

    $url = '/_design/app/_view/count';

    $beforeValue = $this->couch->get($url)->body->rows[0]->value;

    $this->couch->post(new stdClass());

    // Expect previous value
    $this->couch->setStaleDefault(true);
    $this->assertEquals($beforeValue, $this->couch->get($url)->body->rows[0]->value);

    // Expect the new value (we added one doc)
    $this->couch->setStaleDefault(false);
    $this->assertEquals($beforeValue + 1, $this->couch->get($url)->body->rows[0]->value);
  }

  public function test_deleteDB()
  {
    $this->assertTrue($this->couch->deleteDatabase($this->couchDBName)->body->ok);
  }

  public function test_timeoutRWValues()
  {
    //should NOT throw on positive seconds
    try { $this->couch->setRWTimeout(1); $this->assertTrue(true); }
    catch(Exception $e) { $this->assertFalse(true); }

    //should NOT throw on positive seconds && microseconds
    try { $this->couch->setRWTimeout(1, 1); $this->assertTrue(true); }
    catch(Exception $e) { $this->assertFalse(true); }

    //should NOT throw on 0 seconds && positive microseconds
    try { $this->couch->setRWTimeout(0, 1); $this->assertTrue(true); }
    catch(Exception $e) { $this->assertFalse(true); }

    //should throw on 0 timeout
    try { $this->couch->setRWTimeout(0, 0); $this->assertFalse(true); }
    catch(Exception $e) { $this->assertTrue(true); }

    //should throw on negative timeout
    try { $this->couch->setRWTimeout(-1); $this->assertFalse(true); }
    catch(Exception $e) { $this->assertTrue(true); }

    //should throw on negative seconds and positive microseconds
    try { $this->couch->setRWTimeout(-1, 1); $this->assertFalse(true); }
    catch(Exception $e) { $this->assertTrue(true); }
  }

  public function test_loginBadType()
  {
    try
    {
      $this->couch->login('a', 'b', "aasdfsadfasf");
      $this->assertTrue(false);
    }
    catch(SagException $e)
    {
      //We want this exception
      $this->assertTrue(true);
    }
    catch(Exception $e)
    {
      //wrong type of exception
      $this->assertTrue(false);
    }
  }

  public function test_getStats()
  {
    $resp = $this->couch->getStats();
    $this->assertTrue(is_object($resp->body));
    $this->assertTrue(is_object($resp->body->couchdb));
  }

  public function test_setDatabaseAndCreate()
  {
    $dbName = 'bwah2222';

    $this->assertFalse(in_array($dbName, $this->couch->getAllDatabases()->body));

    $this->couch->setDatabase($dbName, true);

    $this->assertEquals($this->couch->get('/')->body->db_name, $dbName);

    $this->couch->deleteDatabase($dbName);

    $this->assertFalse(in_array($dbName, $this->couch->getAllDatabases()->body));

    /*
     * The database is still set internally in Sag's memory but it was also
     * deleted. If we call setDatabase() again with the same db name and tell
     * Sag to also create the database, then it should be created and still
     * have the same internal state.
     *
     * See https://github.com/sbisbee/sag/issues/33
     */
    $this->couch->setDatabase($dbName, true);
    $this->assertEquals($this->couch->currentDatabase(), $dbName);
    $this->assertEquals($this->couch->get('/')->body->db_name, $dbName);

    $this->couch->deleteDatabase($dbName);
    $this->assertFalse(in_array($dbName, $this->couch->getAllDatabases()->body));
  }

  public function test_urlEncodingDatabaseName()
  {
    $this->couch->setDatabase('/test');
    $this->assertEquals('%2Ftest', $this->couch->currentDatabase());
  }

  public function test_settersReturnSag()
  {
    $this->assertEquals($this->couch, $this->couch->decode(true));
    $this->assertEquals($this->couch, $this->couch->setDatabase('bwah'));
    $this->assertEquals($this->couch, $this->couch->setOpenTimeout(1));
    $this->assertEquals($this->couch, $this->couch->setRWTimeout(1));
    $this->assertEquals($this->couch, $this->couch->setCache(new SagMemoryCache()));
    $this->assertEquals($this->couch, $this->couch->setStaleDefault(true));
    $this->assertEquals($this->couch, $this->couch->setCookie('a', 'b'));

    try {
      $this->assertEquals($this->couch, $this->couch->useSSL(false));
      $this->assertEquals($this->couch, $this->couch->setSSLCert(__FILE__));
    }
    catch(SagException $e) {
      //do nothing - not all http libraries support ssl
    }
  }

  public function test_setAndGetCookie() {
    $this->couch->setCookie('foo', 'bar');
    $this->assertEquals($this->couch->getCookie('foo'), 'bar');

    $this->couch->setCookie('foo', null);
    $this->assertEquals($this->couch->getCookie('foo'), null);

    try {
      // should throw
      $this->couch->setCookie(false, 'bar');
      $this->assertTrue(false);
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }

    try {
      // should throw
      $this->couch->setCookie('foo', true);
      $this->assertTrue(false);
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }
  }

  public function test_setSSL() {
    try {
      $this->couch->useSSL('');
      $this->assertTrue(false);
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }

    $this->assertEquals($this->couch, $this->couch->useSSL(false));

    /*
     * Checks normal behavior, but also makes sure that adapters/libraries that
     * do not support SSL throw a SagException.
     */
    try {
      $this->assertEquals($this->couch, $this->couch->useSSL(true));
    }
    catch(SagException $e) {
      if($this->couchHTTPAdapter === Sag::$HTTP_NATIVE_SOCKETS) {
        $this->assertTrue(true);

        // do not support - DONE!
        return;
      }
      else {
        throw $e;
      }
    }
  }

  public function test_usingSSL() {
    $this->assertTrue(is_bool($this->couch->usingSSL()));
  }

  public function test_setSSLCert() {
    if($this->couchHTTPAdapter === Sag::$HTTP_NATIVE_SOCKETS) {
      $this->markTestSkipped('Sag in native sockets mode - skipping SSL test.');
      return;
    }

    // should not throw or error: adapter should just quietly turn off ssl verification
    $this->couch->setSSLCert(null);

    try {
      // should throw
      $this->couch->setSSLCert(false);
      $this->assertTrue(false);
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }

    $file = '/tmp/sag/asdf';
    @unlink($file); //just in case previous run was bad

    $this->assertFalse(is_file($file));

    try {
      // should throw (file doesn't exist)
      $this->couch->setSSLCert($file);
      $this->assertTrue(false);
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }

    $this->assertTrue(touch($file));
    $this->assertInstanceOf('Sag', $this->couch->setSSLCert($file));

    // clean up
    unlink($file);
  }

  public function test_connectionFailure() {
    $badCouch = new Sag('example.com');
    $badCouch->setOpenTimeout(1);

    try {
      $badCouch->setDatabase('bwah');
      $badCouch->get('/asdf');
      $this->assertTrue(false); //shouldn't reach this line
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }
  }
}
