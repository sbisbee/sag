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

require_once("SagCache.php");
require_once("SagException.php");

/**
 * Caches Sag results in memory, using PHP arrays. Be mindful of your PHP
 * install's max memory size.
 * 
 * @package Cache
 * @version 0.2.0
 */
class SagMemoryCache extends SagCache
{
  private $cache = array();

  public function get($url)
  {
    $item = $this->cache[self::makeKey($url)];
    if(!isset($item))
      return null;

    if($this->pruneOnGet && $item->e <= time())
    {
      self::delete($url);
      return null;
    }

    return $item->v;
  }

  public function set($url, $item, $expiresOn = null)
  {
    if(!is_string($url) || empty($url))
      throw new SagException("Invalid URL provided.");

    if(!is_object($item))
      throw new SagException("Invalid item provided.");

    if($expiresOn != null && (!is_int($expiresOn) || $expiresOn <= time()))
      throw new SagException("Invalid expiration provided.");

    $cItem = new StdClass();
    $cItem->v = $item;
    $cItem->e = ($expiresOn) ? $expiresOn : self::getExpiresOn(); 

    $key = self::makeKey($url);

    $old = $this->cache[$key];
    $this->cache[$key] = $cItem;
    return $old;
  }

  public function remove($url)
  {
    if(!is_string($url) || strlen($url) <= 0)
      throw new SagException("Invalid URL provided.");

    $key = self::makeKey($url);
    $old = $this->cache[$key]->v;

    unset($this->cache[$key]);
    return $old;
  }

  public function clear()
  {

  }

  public function prune()
  {

  }
}
