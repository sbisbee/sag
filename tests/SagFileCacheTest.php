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

require_once('SagFileCache.php');

class SagFileTest extends PHPUnit_Framework_TestCase
{
  protected $cache;

  private $FILE_EXT = '.sag';

  public function setUp()
  {
    $this->cache = new SagFileCache('/tmp/sag');
  }

  public function test_createNew()
  {
    foreach(array('/bwah', '/hithere', '/yup') as $url)
    {
      $prevSize = $this->cache->getUsage();

      $item = new stdClass();
      $item->body = new stdClass();
      $item->body->foo = "bar";
      $item->headers = new stdClass();
      $item->headers->etag = "\"asdfasfsadfsadf\"";

      $res = $this->cache->set($url, $item);
      $this->assertTrue($res === true || is_object($res)); 

      $file = '/tmp/sag/'.$this->cache->makeKey($url).$this->FILE_EXT;

      $this->assertTrue(is_file($file));
      $this->assertTrue(is_readable($file));
      $this->assertTrue(is_writable($file));

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
  } 

  public function test_get()
  {
    $this->assertNotNull($this->cache->get('/bwah'));
    $this->assertNull($this->cache->get(rand(0,100)));
  }

  public function test_newVersion()
  {
    $new = new stdClass();
    $new->body = new stdClass();
    $new->body->titFor = "tat";
    $new->headers = new stdClass();
    $new->headers->etag = "\"asdfasdfasdfasdf\"";

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

  public function test_setSize()
  {
    $size = 10;
    $this->cache->setSize($size);
    $this->assertEquals($size, $this->cache->getSize());
  }

  public function test_clear()
  {
    $files = glob('/tmp/sag/*'.$this->FILE_EXT);
    $this->assertFalse(empty($files));

    $this->assertTrue($this->cache->clear());

    $files = glob('/tmp/sag/*'.$this->FILE_EXT);
    $this->assertTrue(empty($files));
  }

  public function test_defaultSizes()
  {
    $this->assertEquals(1000000, $this->cache->getSize());
    $this->assertEquals(0, $this->cache->getUsage());
  }
}
?>
