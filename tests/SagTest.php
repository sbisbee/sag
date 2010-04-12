<?php
require_once('PHPUnit/Framework.php');
require_once('../src/Sag.php');

class SagTest extends PHPUnit_Framework_TestCase
{
  protected $couch;

  public function setUp()
  {
    $this->couch = new Sag('192.168.1.5');
    $this->couch->setDatabase('upholstery_tests');
  }

  public function test_createDB()
  {
    $result = $this->couch->createDatabase('upholstery_tests');
    $this->assertTrue($result->body->ok);
  }

  public function test_allDatabases()
  {
    $result = $this->couch->getAllDatabases();
    $this->assertTrue(in_array('upholstery_tests', $result->body));
  }

  public function test_newDoc()
  {
    $o = new StdClass();
    $o->foo = 'bar';

    $result = $this->couch->put('1', $o); 
    $this->assertTrue($result->body->ok);
    $this->assertEquals($result->body->id, '1');
  }

  public function test_getID()
  {
    $result = $this->couch->get('/1');
    $this->assertEquals($result->body->_id, '1');
    $this->assertEquals($result->body->foo, 'bar');
  }

  public function test_copyToNew()
  {
    $result = $this->couch->copy('1', '1copy');
    $this->assertEquals($result->headers->_HTTP->status, '201');
    $this->assertEquals($result->body->id, '1copy');
  }

  public function test_copyToOverwrite()
  {
    $dst = $this->couch->get('/1copy');
    $result = $this->couch->copy('1', '1copy', $dst->body->_rev);
    $this->assertEquals($result->headers->_HTTP->status, '201');
  }

  public function test_getIDNoDecode()
  {
    $this->couch->decode(false);
    $this->assertTrue(is_string($this->couch->get('/1')->body));
    $this->couch->decode(true);
  }

  public function test_updateDoc()
  {
    //get it...
    $result = $this->couch->get('/1');
    $result->body->foo = 'foo';

    //...send it...
    $result = $this->couch->post($result->body);

    $this->asserttrue($result->body->ok);

    //...and get it again
    $result = $this->couch->get('/1');
    $this->assertEquals($result->body->foo, 'foo');
  }

  public function test_deleteDoc()
  {
    $doc = $this->couch->get('/1');
    $result = $this->couch->delete($doc->body->_id, $doc->body->_rev);
    $this->assertTrue($result->body->ok);

    try
    {
      $doc = $this->couch->get('/1');
      $this->assertTrue(false); //previous line should have thrown an exception
    }
    catch(Exception $e)
    {
      $this->assertEquals($e->getCode(), '404');
    }
  }

  public function test_getAllDocs()
  {
    $result = $this->couch->getAllDocs(true, 0, '""', '[]');
    $this->assertTrue(is_array($result->body->rows));
  }

  public function test_getAllDocsBySeq()
  {
    $result = $this->couch->getAllDocsBySeq(true, 0, '""', '[]');
    $this->assertTrue(is_array($result->body->rows));
  }

  public function test_genIDs()
  {
    $result = $this->couch->generateIDs();
    $this->assertTrue(is_array($result->body->uuids));
  }

  public function test_errorHandling()
  {
    try
    {
      $this->couch->generateIDs(-1); //should throw an Exception
      $this->assertTrue(false);
    }
    catch(Exception $e)
    {
      $this->assertTrue(true);
    }
  }

  public function test_basicAuth()
  {
    $this->couch->login("foo", "bar");
    print_r($this->couch->get('1'));
  }

  public function test_bulk()
  {
    $docs = array();
    
    $a = new StdClass();
    $a->foo = "bar";
    $a->bwah = "hi";

    $b = new StdClass();
    $b->hi = "there";
    $b->lo = "fi";

    array_push($docs, $a, $b);

    $result = $this->couch->bulk($docs);
    $this->assertTrue(is_array($result->body));
    
    $doc = $this->couch->get('/'.$result->body[0]->id);
    $this->assertTrue($doc->body->foo == $a->foo);
    $this->assertTrue($doc->body->bwah == $a->bwah);
  }

  public function test_deleteDB()
  {
    $result = $this->couch->deleteDatabase('upholstery_tests');
    $this->assertTrue($result->body->ok);
  }
}
?>
