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

// See the README in tests/ for information on running and writing these tests.

require_once('PHPUnit/Framework.php');
require_once('../src/Sag.php');
require_once('../src/SagFileCache.php');

class SagTest extends PHPUnit_Framework_TestCase
{
  protected $couch;

  public function setUp()
  {
    $this->couch = new Sag('192.168.1.5');
    $this->couch->setDatabase('sag_tests');
  }

  public function test_createDB()
  {
    $result = $this->couch->createDatabase('sag_tests');
    $this->assertTrue($result->body->ok);
  }

  public function test_allDatabases()
  {
    $this->assertTrue(in_array('sag_tests', $this->couch->getAllDatabases()->body));
  }

  public function test_newDoc()
  {
    $doc = new StdClass();
    $doc->foo = 'bar';

    $result = $this->couch->put('1', $doc); 
    $this->assertTrue($result->body->ok);
    $this->assertEquals($result->body->id, '1');
  }

  public function test_getID()
  {
    $result = $this->couch->get('/1');
    $this->assertEquals($result->body->_id, '1');
    $this->assertEquals($result->body->foo, 'bar');

    //make sure we're prepending slashes when they're not present
    $this->assertEquals($result->body->_id, $this->couch->get('1')->body->_id);
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
    $this->assertTrue(is_array(
      $this->couch->getAllDocs(true, 0, '""', '[]')->body->rows
    ));

    $this->assertEquals('1', 
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

    $result = $this->couch->bulk(array($a, $b));
    $this->assertTrue(is_array($result->body));
    
    $doc = $this->couch->get('/'.$result->body[0]->id);
    $this->assertEquals($doc->body->foo, $a->foo);
    $this->assertEquals($doc->body->bwah, $a->bwah);
  }

  public function test_replication()
  {
    $newDB = "sag_tests_replication";
    
    $this->assertFalse(in_array($newDB, $this->couch->getAllDatabases()->body));
    $this->assertTrue($this->couch->createDatabase($newDB)->body->ok);
    $this->assertTrue($this->couch->replicate('sag_tests', $newDB)->body->ok);
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
    $this->assertTrue($this->couch->deleteDatabase('sag_tests')->body->ok);
  }

  public function test_connectionFailure()
  {
    $badCouch = new Sag('example.com');
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
}
?>
