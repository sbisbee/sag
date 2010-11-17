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

require_once('SagCache.php');
require_once('SagException.php');

/*
 * Cache to the local hard disk. Uses the system's default temp directory by
 * default, but you can specify another location.
 *
 * Cache keys are used for file names, and the contents are JSON. System file
 * sizes are used to calculate the cache's current size.
 *
 * @package Cache 
 * @version 0.2.0
 */
class SagMemoryCache extends SagCache 
{
  private $cache;

  public function SagFileCache()
  {
    $this->cache = array();
  }

  public function set($url, $item)
  {
    if(empty($url))
      throw new SagException('You need to provide a URL to cache.');

    if(!parent::mayCache($item))
      return false;

    // If it already exists, then remove the old version but keep a copy
    if($this->cache[$url])
    {
      $oldCopy = $this->cache[$url];
      self::remove($url);
    }

    $prevSize = memory_get_usage();
    $this->cache[$url] = $item;
    self::addToSize(memory_get_usage() - $prevSize);

    return (isset($oldCopy) && is_object($oldCopy)) ? $oldCopy : true;
  }

  public function get($url)
  {
    return $this->cache[$url];
  }

  public function remove($url)
  {
    $prevSize = memory_get_usage();
    unset($this->cache[$url]);
    self::addToSize(-($prevSize - memory_get_usage()));
    return true;
  }

  public function clear()
  {
    unset($this->cache);
    $this->cache = array();
    return true;
  }
} 
?>
