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

require_once('PHPUnit/Framework.php');
require_once('../src/Sag.php');
require_once('../src/SagFileCache.php');

class SagTest extends PHPUnit_Framework_TestCase
{
  protected $couchIP;
  protected $couchDBName;
  protected $couchAdminName;
  protected $couchAdminPass;

  protected $couch;
  protected $session_couch;

  public function setUp()
  {
    $this->couchIP = '127.0.0.1';
    $this->couchDBName = 'sag_tests';
    $this->couchAdminName = 'sam bisbee';
    $this->couchAdminPass = 'hi/there';

    $this->couch = new Sag($this->couchIP);
    $this->couch->login($this->couchAdminName, $this->couchAdminPass);
    $this->couch->setDatabase($this->couchDBName);

    $this->session_couch = new Sag($this->couchIP);
    $this->session_couch->setDatabase($this->couchDBName);
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
    $doc = new StdClass();
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

  public function test_getID()
  {
    $result = $this->couch->get('/1');
    $this->assertEquals($result->body->_id, '1');
    $this->assertEquals($result->body->foo, 'bar');

    //make sure we're prepending slashes when they're not present
    $this->assertEquals($result->body->_id, $this->couch->get('1')->body->_id);
  }

  public function test_postAllDocs()
  {
    $result = $this->couch->post(array('keys' => array('1')), '/_all_docs');
    $this->assertEquals(1, sizeof($result->body->rows));
    $this->assertEquals("1", $result->body->rows[0]->id);
    
    $validData = new StdClass();
    $invalidPaths = array(array(), "", new StdClass(), false, true);

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
    $data = new StdClass();
    $data->map = 'function(doc) { emit(doc._id, 1); }';
    $data->reduce = '_sum';

    $result = $this->couch->post($data, '/_temp_view')->body->rows[0]->value;
    $this->assertTrue(is_int($result));
    $this->assertTrue($result > 0);
  }

  public function test_getIDNoDecode()
  {
    $this->couch->decode(false);

    $json = $this->couch->get('/1')->body;
    $this->assertTrue(is_string($json));

    $this->couch->decode(true); //for the benefit of future tests
    $this->assertEquals(json_decode($json)->_id, '1');
  }

  public function test_updateDoc()
  {
    //get it and set it...
    $doc = $this->couch->get('/1')->body;
    $doc->foo = 'foo';

    //...send it...
    $this->assertTrue($this->couch->post($doc)->body->ok);

    //...and get it again
    $this->assertEquals($this->couch->get('/1')->body->foo, 'foo');
  }

  public function test_getAllDocs()
  {
    $resDefaults = $this->couch->getAllDocs();
    $this->assertTrue(is_array($resDefaults->body->rows));
    $this->assertTrue(isset($resDefaults->body->rows[0]));
    $this->assertTrue(isset($resDefaults->body->rows[0]->value));
    $this->assertFalse(isset($resDefaults->body->rows[0]->doc));

    $resAllWithDocs = $this->couch->getAllDocs(true, null, '""', '[]');
    $this->assertTrue(is_array($resAllWithDocs->body->rows));
    $this->assertTrue(isset($resAllWithDocs->body->rows[0]->value));
    $this->assertTrue(isset($resAllWithDocs->body->rows[0]->doc));
    $this->assertEquals(
              sizeof($resDefaults->body->rows), 
              sizeof($resAllWithDocs->body->rows)
    );

    $resLimitZero = $this->couch->getAllDocs(false, 0);
    $this->assertTrue(is_array($resLimitZero->body->rows));
    $this->assertTrue(empty($resLimitZero->body->rows)); 

    $this->assertEquals(
              '1',
              $this->couch->getAllDocs(true, null, null, null, array("1"))->body->rows[0]->id
    );
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
    $a = new StdClass();
    $a->foo = "bar";
    $a->bwah = "hi";

    $b = new StdClass();
    $b->hi = "there";
    $b->lo = "fi";

    $c = new StdClass();
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
    $newDB = "sag_tests_replication";

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

    $this->assertTrue($this->couch->deleteDatabase($newDB)->body->ok);
  }

  public function test_compactView()
  {
    $designID = "bwah";

    $ddoc = new StdClass();
    $ddoc->_id = "_design/$designID";
    $ddoc->language = "javascript";
    $ddoc->views = new StdClass();
    $ddoc->views->all = new StdClass();
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

    // Check contents - text/plain gets base64 encoded
    $this->assertEquals($data, base64_decode($res->body->_attachments->{$name}->data));

    // Check contents, via stand alone
    $this->assertEquals($data, $this->couch->get("/$docID/$name")->body);
  }

  public function test_createSession()
  {
    try
    {
      $this->session_couch->login($this->couchAdminName, $this->couchAdminPass, Sag::$AUTH_COOKIE);
    }
    catch(Exception $e)
    {
      //should not happen - FAIL
      $this->assertTrue(false);
    }
  }

  public function test_createDocWithSession()
  {
    $db = new Sag($this->couchIP);
    $db->setDatabase($this->couchDBName);
    $db->login($this->couchAdminName, $this->couchAdminPass, Sag::$AUTH_COOKIE);

    $doc = new StdClass();
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
  }

  public function test_getFromCache()
  {
    $cache = new SagFileCache('/tmp/sag');
    $this->couch->setCache($cache);

    $doc = new StdClass();
    $doc->hi = "there";

    $id = $this->couch->post($doc)->body->id;
    
    //doc creation is not cached
    $cFileName = $cache->makeFilename("/{$this->couch->currentDatabase()}/$id");
    $this->assertFalse(is_file($cFileName));

    $fromDB = $this->couch->get("/$id");

    //should now be cached
    $this->assertTrue(is_file($cFileName));
    $this->assertEquals(json_encode($fromDB), file_get_contents($cFileName));
  }

  public function test_deleteDB()
  {
    $this->assertTrue($this->couch->deleteDatabase($this->couchDBName)->body->ok);
  }

  public function test_connectionFailure()
  {
    $badCouch = new Sag('example.com');
    $badCouch->setOpenTimeout(1);

    try
    {
      $badCouch->setDatabase('bwah');
      $badCouch->get('/asdf');
      $this->assertTrue(false); //shouldn't reach this line
    }
    catch(SagException $e)
    {
      $this->assertTrue(true);
    }
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
    $dbName = 'bwah';

    $this->assertFalse(in_array($dbName, $this->couch->getAllDatabases()->body));

    $this->couch->setDatabase($dbName, true);

    $this->assertEquals($this->couch->get('/')->body->db_name, $dbName);

    $this->couch->deleteDatabase($dbName);

    $this->assertFalse(in_array($dbName, $this->couch->getAllDatabases()->body));
  }
}
