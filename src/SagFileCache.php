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
class SagFileCache extends SagCache 
{
  private static $fileExt = ".sag";

  private $fsLocation;

  /**
   * @param string $location The file system path to the directory that should
   * be used to store the cache files. The local system's temp directory is
   * used by default.
   * @return SagFileCache
   */
  public function SagFileCache($location)
  {
    if(!is_dir($location))
      throw new SagException("The provided cache location is not a directory.");

    if(!is_readable($location) || !is_writable($location))
      throw new SagException("Insufficient privileges to the supplied cache directory.");

    parent::SagCache();

    $this->fsLocation = rtrim($location, "/ \t\n\r\0\x0B");

    /* 
     * Just update - don't freak out if the size isn't right, as the user might
     * update it to non-default, they might not do anything with the cache,
     * they might clean it themselves, etc. give them time. We'll freak when we
     * add.
     */
    foreach(glob($this->fsLocation."/*".self::$fileExt) as $file)
      $this->currentSize += filesize($file);
  }   

  /**
   * Generates the full filename/path that would be used for a given URL's
   * cache object.
   *
   * @param string $url The URL for the cached item.
   * @return string
   */
  private function makeFilename($url)
  {
    return "$this->fsLocation/".self::makeKey($url).self::$fileExt;
  }

  public function set($url, $item, $expiresOn = null)
  {
    if(
      empty($url) || 
      (
        !is_int($expiresOn) &&
        $expiresOn <= time() &&
        $expiresOn != null
      ) 
    )
      throw new SagException("Invalid parameters for caching.");

    $toCache = new StdClass();
    $toCache->e = ($expiresOn == null) ? $this->defaultExpiresOn : $expiresOn;
    $toCache->v = $item; 
    $toCache = json_encode($toCache);

    $target = self::makeFilename($url);

    if(is_file($target))
    {
      if(!is_readable($target) || !is_writable($target))
        throw new Exception("Could not read the cache file for URL: $url - please check your file system privileges.");

      $oldSize = filesize($target);
      if($this->currentSize - $oldSize + strlen($toCache) > $this->getSize())
        return false;

      $fh = fopen($target, "r+");

      $oldCopy = json_decode(fread($fh, $oldSize));

      ftruncate($fh, 0);
      $this->currentSize -= $oldSize;

      unset($oldSize);

      rewind($fh);
    }
    else
    {
      $estSize = $this->currentSize + strlen($toCache);

      if($estSize >= disk_free_space("/") * .95)
        throw new Exception("Trying to cache to a disk with low free space - refusing to cache.");

      if($estSize > $this->getSize())
        return false;

      $fh = fopen($target, "w");
    }

    fwrite($fh, $toCache, strlen($toCache)); //don't throw up if we fail - we're not mission critical
    $this->currentSize += filesize($file);

    fclose($fh);

    return (is_object($oldCopy) && ($oldCopy->e == null || $oldCopy->e < time())) ? $oldCopy->v : true;
  }

  public function get($url)
  {
    $target = $this->makeFilename($url);
    if(!is_file($target))
      return null;

    if(!is_readable($target))
      throw new SagException("Could not read the cache file at $target - please check its permissions.");

    $item = json_decode(file_get_contents($target));
    return ($item->e < time()) ? $item->v : false; 
  }

  public function remove($url)
  {
    $target = $this->makeFilename($url);
    if(!is_file($target))
      return true;

    if(!is_writable($target))
      throw new SagException("Not able to read the cache file at $target - please check its permissions.");

    $oldSize = filesize($target);
    $suc = @unlink($target);
    if(!$suc)
      return false;

    $this->currentSize -= $oldSize;
    return $suc;
  }

  public function clear()
  {
    $part = false;
    foreach(glob($this->fsLocation."/*".self::$fileExt) as $file)
    {
      if(is_writable($file))
      {
        $oldSize = filesize($file);
        if(@unlink($file))
          $this->currentSize -= $oldSize;
        else
          $part = true;
      }
      else
        $part = true;
    } 

    if($this->currentSize < 0)
      $this->currentSize = 0; //shouldn't happen, but whatever

    return !$part;
  }

  public function prune()
  {
    $numDel = 0;

    foreach(glob($this->fsLocation."/*".self::$fileExt) as $file)
    {
      if(is_readable($file))
      {
        $item = json_decode(file_get_contents($file));
        if($item->e >= time())
        {
          $oldSize = filesize($file);
          if(@unlink($file))
          {
            $this->currentSize -= $oldSize;
            $numDel++;
          }
          else
            throw new SagException("Unable to prune a cache file at $file.");
        }
      }
      else
        throw new SagException("Unable to read a cache file at $file."); 
    }

    return $numDel;
  }
} 
?>
