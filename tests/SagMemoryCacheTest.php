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

require_once('PHPUnit/Framework.php');
require_once('../src/SagMemoryCache.php');

class SagMemoryTest extends PHPUnit_Framework_TestCase
{
  protected $cache;

  public function setUp()
  {
    $this->cache = new SagMemoryCache();
  }

  public function test_setGet()
  {
    $url = "http://www.example.com:5984/somedb/bwah";

    $item = new StdClass();
    $item->body = new StdClass();
    $item->response = new StdClass();
    $item->response->_id = "bwah";
    $item->response->foo = "bar";

    $this->cache->set($url, $item);

    $this->assertEquals($item, $this->cache->get($url));
  }

  public function test_setOldExpiration()
  {
    try
    {
      $this->cache->set("url", new StdClass(), 100);
      $this->assertTrue(false);
    }
    catch(SagException $e)
    {
      $this->assertTrue(true);
    }
    catch(Exception $e)
    {
      $this->assertTrue(false);
    }
  }

  public function test_removeSomething()
  {
    $url = "http://www.example.com:5984/somedb/bwah";
    $item = new StdClass();
    $item->bwah = "w000";

    $this->cache->set($url, $item);
    $this->assertEquals($item, $this->cache->remove($url));

    $this->assertNull($this->cache->get($url));
  }
}
?>
