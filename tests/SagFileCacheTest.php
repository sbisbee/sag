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
require_once('../src/SagFileCache.php');

class SagFileTest extends PHPUnit_Framework_TestCase
{
  protected $cache;

  public function setUp()
  {
    $this->cache = new SagFileCache('/tmp/sag');
  }

  public function test_createNew()
  {
    $url = '/bwah';

    $item = new StdClass();
    $item->body = new StdClass();
    $item->body->foo = "bar";
    $item->bwah = 123;

    $res = $this->cache->set($url, $item);
    $this->assertTrue($res === true || is_object($res)); 

    $file = '/tmp/sag/'.$this->cache->makeKey($url).'.sag';

    $this->assertTrue(
      is_file($file) &&
      is_readable($file) &&
      is_writable($file)
    );

    $this->assertEquals(
      $item,
      json_decode(file_get_contents($file))->v
    );

    $this->assertEquals(filesize($file), $this->cache->getUsage());
  } 

  public function test_get()
  {
    $this->assertTrue(is_object($this->cache->get('/bwah')));
    $this->assertNull($this->cache->get(rand(0,100)));
  }

  public function test_delete()
  {
    $this->assertTrue(is_object($this->cache->get('/bwah')));
    $this->assertTrue($this->cache->remove('/bwah'));
    $this->assertNull($this->cache->get('/bwah'));
  }

  public function test_pruneOnGet()
  {
    $url = '/soonToDie';

    $this->assertTrue($this->cache->set($url, array(), strtotime('+1 second')));
    sleep(1); //should be expired now
     
    $file = '/tmp/sag/'.$this->cache->makeKey($url).'.sag';

    //get without pruneOnGet set to true
    $this->assertFalse($this->cache->get($url));
    $this->assertTrue(is_file($file));

    //now get with pruneOnGet set to true
    $this->cache->pruneOnGet(true);

    $this->assertFalse($this->cache->get($url));
    $this->assertFalse(is_file($file));
  }

  public function test_setSize()
  {
    $size = 10;
    $this->cache->setSize($size);
    $this->assertEquals($size, $this->cache->getSize());
  }

  public function test_pruneOnExceedSet()
  {
    $firstURL = '/first';
    $firstData = '123456789';
    $secondURL = '/second';
    $secondData = '123';

    $this->assertTrue($this->cache->set($firstURL, $firstData, strtotime('+1 second')));
    sleep(1); //it's now expired, and would get pruned

    $this->cache->setSize(26); //adding anything else should exceed
  
    //pruneOnExceed should be defaulted to false
    try
    {
      $this->cache->set($secondURL, $secondData); 

      $this->assertTrue(false); //...but apparently didn't
    }
    catch(SagException $e)
    {
      $this->assertTrue(true); //...and did
    }
    catch(Exception $e)
    {
      $this->assertTrue(false); //...and did, but wrong Exception type
    }

    //And again, with pruning.
    $this->cache->pruneOnExceed(true);
 
    $this->assertTrue($this->cache->set($secondURL, $secondData));

    $this->assertNull($this->cache->get($firstURL));
    $this->assertEquals($this->cache->get($secondURL), $secondData);
  }

  public function test_clear()
  {
    $this->assertTrue($this->cache->clear());

    $files = glob('/tmp/sag/*.sag');
    $this->assertTrue(empty($files));
  }
}
?>
