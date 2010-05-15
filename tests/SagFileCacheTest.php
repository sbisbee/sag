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

    $expireOn = time() + 100;

    $this->cache->set($url, $item, $expireOn);

    $expectedCache = new StdClass();
    $expectedCache->e = $expireOn;
    $expectedCache->v = $item;

    $this->assertEquals(
      json_encode($expectedCache), 
      file_get_contents('/tmp/sag/'.$this->cache->makeKey($url).'.sag')
    );
    echo(strlen(json_encode($expectedCache)));
  } 
}
?>
