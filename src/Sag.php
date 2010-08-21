#!/usr/bin/php
<?php
/*
 * Returns false if the page could not be retrieved (ie., no 2xx or 3xx HTTP
 * status code). On success, if $includeContents = false (default), then we
 * return true - if it's true, then we return file_get_contents()'s result (a
 * string of page content).
 */
function getURL($url, $port = 80, $includeContents = false)
{
  if(empty($url) || !is_string($url) || substr($url, 0, 5) == "http:" || substr($url, 0, 6) == "https:")
    throw new Exception("Invalid host");

  if(!is_int($port) && $port <= 0)
    throw new Exception("Invalid port");

  if(!is_bool($includeContents))
    throw new Exception('Unexpected value for $includeContents');

  $sock = @fsockopen($url, $port);

  if($sock)
  {
    if($includeContents)
      $buffOut = ""; //this is what we'll use to store the page's contents

    fwrite($sock, "GET / HTTP/1.0\r\nHost: $url\r\n\r\n");

    $sockInfo = stream_get_meta_data($sock);
    $isHeader = true;           //whether we're processing HTTP headers or not 
    $isStatusFound = false;     //whether we've read in the HTTP code yet or not

    while(!feof($sock))
    {
      if($sockInfo['timed_out'])
        return false;

      $line = fgets($sock);

      if($isHeader)
      {
        $line = trim($line);

        if(empty($line))
          $isHeader = false;
        elseif(!$isStatusFound && preg_match('(^HTTP/\d+\.\d+\s+(?P<status>\d+))S', $line, $match))
        {
          $c = substr($match['status'], 0, 1);

          //TODO if we want to follow a 3xx to its destination, then we'd
          //detect it here and do a redursive call

          if($c != '2' && $c != '3')
            return false;

          if(!$includeContents)
          {
            //let's hope they're using a sane web server and don't mind an abrupt close
            fclose($sock);
            return true;
          }

          $isStatusFound = true;
        }
      }
      else
        $buffOut .= $line;
    }

    return $buffOut;
  }

  return false; //be cynical and assume failure if we've gotten this far
}

var_dump(getURL("gabadoo.com", 80, false));