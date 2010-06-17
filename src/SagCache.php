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

require_once("SagException.php");

/**
 * All the caching systems that Sag can leverage must extend this. The cache
 * values should always be the object that Sag::procPacket() would return.
 *
 * Cached items will expire in 30 days by default. All cache expiration math
 * uses PHP's time(), and therefore may not correspond to when the request was
 * made. 
 *
 * The default cache size is 1MB (one million bytes).
 *
 * Cache values are objects (StdClass for PHP storage, or JSON for external
 * storage):
 * {
 *   "e": Unix time stamp (expires on),
 *   "v": value
 * }
 *
 * @package Cache 
 * @version 0.2.0
 */
abstract class SagCache 
{
  private $defaultExpiresOn;                            //Unix time stamp
  private $defaultSize = 1000000;                       //in bytes

  protected $currentSize;                               //in bytes

  protected $pruneOnGet = false;
  protected $pruneOnExceed = false;

  public function SagCache()
  {
    $this->defaultExpiresOn = strtotime("+30 days");
  }

  /** 
   * Returns the cached object, false if it expired, or null if nothing is
   * cached. Ex., if false is returned, then you may want to call prune() to
   * clear all expired items or remove().
   * 
   * @param string $url The URL of the request we're caching.  
   * @return object
   */
  abstract public function get($url);

  /**
   * Caches the item returned at the provided key, replacing any pre-existing
   * cached item. If the cache's size will be exceeded by caching the new item,
   * then it will remove items from the cache until there is sufficient room.
   *
   * Returns false if adding the item would exceed the cache size.
   *
   * Returns true if we were able to add the item, and there was no previously
   * cached item or the previously cached item is expired.
   *
   * Returns the previously cached item if there was one and we were able to
   * add the new item to the cache.
   *
   * Sag will refuse to cache the object by throwing a SagException if adding
   * the file to the cache would exceed 95% of the disk or partition's free
   * space.
   *
   * @param string $url The URL of the request we're caching.
   * @param object $item The response we're caching.
   * @param int $expiresOn A Unix time stamp of when you want this item to
   * expire in the cache. Pass null or omit to use the default expiration time.
   * @return mixed
   */
  abstract public function set($url, $item, $expiresOn = null);

  /**
   * Removes the item from the cache and returns it (null if nothing was
   * cached).
   *
   * @param string $url The URL of the response we're removing from the cache.
   * @return mixed
   */
  abstract public function remove($url);

  /**
   * Clears the whole cache without applying any logic.
   *
   * Returns true if the entire cache was cleared, otherwise false if only part
   * or none of it was cleared.
   *
   * @return bool
   */
  abstract public function clear();

  /**
   * Removes all expired items from the cache. Returns the number of items that
   * were deleted from the cache.
   */
  abstract public function prune();

  /**
   * Set whether you want the cache to remove expired items from the cache when
   * you attempt to retrieve them. If you don't want the cache to, then the
   * cache may remain dirty. If you do want the cache to, then there may be a
   * delay while the cache deletes the expired item.
   *
   * Defaults to false.
   *
   * @param bool $prune
   */
  public function pruneOnGet($prune)
  {
    if(!is_bool($prune))
      throw new Exception("Expected a bool.");

    $this->pruneOnGet = $prune; 
  }

  /**
   * Set whether you want the cache to run prune() when you ask it to cache an
   * item that would cause the cache's size to be exceeded. The prune would run
   * before the cache attempts to remove items from the cache, making room for
   * the new item.
   *
   * Defaults to false.
   *
   * @param bool $prune
   */
  public function pruneOnExceed($prune)
  {
    if(!is_bool($prune))
      throw new Exception("Expected a bool.");

    $this->pruneOnExceed = $prune;
  }

  /**
   * Sets the size of the cache in bytes.
   * 
   * @param int $bytes The size of the cache in bytes (>0).
   */
  public function setSize($bytes)
  {
    if(!is_int($bytes) || $bytes <= 0)
      throw new Exception("The cache size must be a positive integer (bytes).");

    $this->defaultSize = $bytes;
  }

  /**
   * Returns the size of the cache, irrespective of what is or isn't in the
   * cache.
   *
   * @return int
   */
  public function getSize()
  {
    return $this->defaultSize;
  }

  /**
   * Returns the total size of the items in the cache in bytes.
   * 
   * @return int
   */
  public function getUsage()
  {
    return $this->currentSize;
  }

  /**
   * Sets the default expiration Unix time stamp for all future caching
   * operations. Must be greather than time()'s return value.
   *
   * @param int $when The Unix time stamp
   */
  public function setExpiresOn($when)
  {
    if(!is_int($when) || $when <= time())
      throw new Exception("Cache's default expiration must be in the future.");

    $this->defaultExpiresOn = $when;
  }

  /**
   * Returns the expiration date being used when caching items.
   *
   * @return int
   */
  public function getExpiresOn()
  {
    return $this->defaultExpiresOn;
  }

  /**
   * Generates the hash of the provided URL that will be used as the cache key.
   *
   * @param string $url The URL to hash.
   * @return string
   */
  public function makeKey($url)
  {
    return sha1($url);
  }
}
