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

require_once('SagMemoryCache.php');

class SagMemoryCacheTest extends PHPUnit_Framework_TestCase
{
  protected $cache;

  public function setUp()
  {
    $this->cache = new SagMemoryCache();
  }

  public function test_createAndGet()
  {
    $url = '/bwah';

    $item = new stdClass();
    $item->body = new stdClass();
    $item->body->foo = "bar";
    $item->headers = new stdClass();
    $item->headers->etag = "\"asdfasfsadfsadf\"";

    $res = $this->cache->set($url, $item);
    $this->assertTrue($res === true || is_object($res));

    $fromCache = $this->cache->get($url);

    $this->assertEquals($item, $fromCache);

    //make sure we aren't doing any funky pointer stuff
    unset($item);
    $this->assertTrue(is_object($fromCache));
    $this->assertTrue(is_object($this->cache->get($url)));
  }

  public function test_remove()
  {
    $url = '/aa';

    $item = new stdClass();
    $item->body = new stdClass();
    $item->body->foo = "bar";
    $item->headers = new stdClass();
    $item->headers->etag = "\"asdfasfsadfsadf\"";

    $this->cache->set($url, $item);
    $this->assertTrue(is_object($this->cache->get($url)));
    $this->cache->remove($url);
    $this->assertFalse(is_object($this->cache->get($url)));
  }

  public function test_overwrite()
  {
    $url = '/bb';

    $item = new stdClass();
    $item->body = new stdClass();
    $item->body->foo = "bar";
    $item->headers = new stdClass();
    $item->headers->etag = "\"asdfasfsadfsadf\"";

    $this->cache->set($url, $item);

    //local update shouldn't update cache
    $item->body->foo = "hi there";
    $fromCache = $this->cache->get($url);
    $this->assertNotEquals(spl_object_hash($item), spl_object_hash($fromCache));

    //update cache, so should be the same now
    $this->cache->set($url, $item);
    $fromCache = $this->cache->get($url);
    $this->assertEquals($item, $fromCache); //same value, but...
    $this->assertNotEquals(spl_object_hash($item), spl_object_hash($fromCache)); //...not the same reference

    //test local deletion
    unset($fromCache);
    $this->assertNotNull($this->cache->get($url)); 
  }

  public function test_clear()
  {
    $item = new stdClass();
    $item->body = new stdClass();
    $item->body->foo = "bar";
    $item->headers = new stdClass();
    $item->headers->etag = "\"asdfasfsadfsadf\"";

    $this->cache->set('/123', $item);
    
    $this->assertTrue($this->cache->clear());

    $this->assertEquals($this->cache->get('/123'), null);
  }

  public function test_memoryMath()
  {
    try {
      $this->cache->getUsage();
      $this->assertTrue(false);
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }
  }

  public function test_cacheBadBody()
  {
    $item = new stdClass();
    $item->headers = new stdClass();
    $item->headers->etag = "\"asdfasfsadfsadf\"";

    //try without a body
    $this->assertFalse($this->cache->set('/hi', $item));

    //try with an invalid body
    $item->body = 123;
    $this->assertFalse($this->cache->set('/hi', $item));
  }
}
?>
