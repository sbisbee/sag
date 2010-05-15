<?php
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

    $this->fsLocation = rtrim($location, "/ \t\n\r\0\x0B");
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
      !is_int($expiresOn) || 
      (
        $expiresOn <= time() &&
        $expiresOn != null
      ) 
    )
      throw new SagException("Invalid parameters for caching.");

    $file = self::makeFilename($url);
    $oldCopy = null;

    //We don't allow symlinks, because when we recreate it won't be a symlink
    //any longer.
    if(file_exists($file) && is_file($file))
    {
      if(!is_readable($file) || !is_writable($file))
        throw new Exception("Could not read the cache file for URL: $url - please check your file system privileges.");

      $fh = fopen($file, "r+");

      $oldCopy = fread($fh);

      ftruncate($fh);
      rewind($fh);
    }
    else
      $fh = fopen($file, "w");

    $cache = new StdClass();
    $cache->e = ($expiresOn == null) ? self::$defaultExpiresOn : $expiresOn;
    $cache->v = $item; 

    fwrite($fh, json_encode($cache)); //don't throw up if we fail - we're not mission critical
    fclose($fh);

    //wait until we close the file to do this
    if($oldCopy != null)
    {
      $oldCopy = json_decode($oldCopy);
      $oldCopy = ($oldCopy->e == null || $oldCopy->e < time()) ? $oldCopy->v : null;
    }

    return $oldCopy;
  }

  public function get($url)
  {

  }

  public function remove($url)
  {

  }

  public function clear()
  {

  }

  public function prune()
  {

  }
} 
?>
