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
    $prevSize = $this->cache->getUsage();

    $url = '/bwah';

    $item = new StdClass();
    $item->body = new StdClass();
    $item->body->foo = "bar";
    $item->headers = new StdClass();
    $item->headers->Etag = "\"asdfasfsadfsadf\"";

    $res = $this->cache->set($url, $item);
    $this->assertTrue($res === true || is_object($res)); 

    $file = '/tmp/sag/'.$this->cache->makeKey($url).'.sag';

    $this->assertTrue(
      is_file($file) &&
      is_readable($file) &&
      is_writable($file)
    );

    $fileContents = file_get_contents($file);
    //compare objects as PHP classes
    $this->assertEquals(json_decode(file_get_contents($file)), $item);
    //compare objects as JSON
    $this->assertEquals($fileContents, json_encode($item));
    //compare sizes as JSON
    $this->assertEquals(strlen($fileContents), strlen(json_encode($item)));
    //compare size on disk with cache's reported size
    $this->assertEquals(filesize($file), $this->cache->getUsage() - $prevSize);
  } 

  public function test_get()
  {
    $this->assertNotNull($this->cache->get('/bwah'));
    $this->assertNull($this->cache->get(rand(0,100)));
  }

  public function test_newVersion()
  {
    $new = new StdClass();
    $new->body = new StdClass();
    $new->body->titFor = "tat";
    $new->headers = new StdClass();
    $new->headers->Etag = "\"asdfasdfasdfasdf\"";

    $file = $this->cache->makeFilename('/bwah');

    $oldContents = file_get_contents($file);
    
    $oldCopy = $this->cache->set('/bwah', $new);
    $this->assertEquals(json_encode($oldCopy), $oldContents); 
    $this->assertEquals($new, json_decode(file_get_contents($file)));
  }

  public function test_delete()
  {
    $this->assertNotNull($this->cache->get('/bwah'));
    $this->assertTrue($this->cache->remove('/bwah'));
    $this->assertNull($this->cache->get('/bwah'));
  }

  public function test_partialClear()
  {
    $file = array_shift(glob('/tmp/sag/*.sag'));
    $this->assertTrue(is_file($file));
    
    //block ourselves so we do a partial clear
    $this->assertTrue(chmod($file, 0111));
    $this->assertFalse($this->cache->clear());

    //reset for future operations
    $this->assertTrue(chmod($file, 0777));
  }

  public function test_setSize()
  {
    $size = 10;
    $this->cache->setSize($size);
    $this->assertEquals($size, $this->cache->getSize());
  }

  public function test_clear()
  {
    $this->assertTrue($this->cache->clear());

    $files = glob('/tmp/sag/*.sag');
    $this->assertTrue(empty($files));
  }

  public function test_defaultSizes()
  {
    $this->assertEquals(1000000, $this->cache->getSize());
    $this->assertEquals(0, $this->cache->getUsage());
  }
}
?>
