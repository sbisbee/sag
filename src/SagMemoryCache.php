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

require_once('SagCache.php');
require_once('SagException.php');

/**
 * Stores cached items in PHP's memory as serialized JSON, which was
 * benchmarked as being faster than serliaze() and clone.
 *
 * Memory sizes will not be exact because of how PHP allocates and cleans
 * memory.
 *
 * @package Cache 
 * @version 0.6.0
 */
class SagMemoryCache extends SagCache {
  private $cache;

  public function __construct() {
    parent::__construct();
    $this->cache = array();
  }

  public function set($url, &$item) {
    if(empty($url)) {
      throw new SagException('You need to provide a URL to cache.');
    }

    if(!parent::mayCache($item)) {
      return false;
    }

    // If it already exists, then remove the old version but keep a copy
    if(isset($this->cache[$url])) {
      $oldCopy = json_decode($this->cache[$url]);
      self::remove($url);
    }

    $serialized = $this->cache[$url] = $prevSize = $itemSize = 1; //for more accurate math
    $prevSize = memory_get_usage();
    $serialized = json_encode($item);
    $itemSize = memory_get_usage() - $prevSize;
    self::addToSize($itemSize);

    $this->cache[$url] = $serialized;

    return (isset($oldCopy) && is_object($oldCopy)) ? $oldCopy : true;
  }

  public function get($url) {
    return (isset($this->cache[$url])) ? json_decode($this->cache[$url]) : null;
  }

  public function remove($url) {
    $prevSize = $removedSize = 1;
    $prevSize = memory_get_usage();

    unset($this->cache[$url]);

    $removedSize = memory_get_usage() - $prevSize;
    self::addToSize($removedSize);

    return true;
  }

  public function clear() {
    unset($this->cache);
    $this->cache = array();
    self::addToSize(-(self::getUsage()));
    return true;
  }
} 
?>
