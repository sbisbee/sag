<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>phpsessions example runner</title>
  </head>
  <body>
    <p>
      <strong>This page runs this code:</strong>

    <pre>
ini_set('display_errors', true);

require_once 'CouchSessionStore.php';
require_once '../../src/Sag.php';

$sag = new Sag();
$sag->login('admin', 'password');
CouchSessionStore::setSag($sag);

session_start();
var_dump($_SESSION);
echo "&lt;br/&gt;";

if(!$_SESSION['userID'])
{
  //Get user's info from the database ... or just hardcode it.
  $_SESSION['userID'] = 100;
  $_SESSION['firstName'] = "Sam";
}

printf("Welcome back %s (#%s)!&lt;br/&gt;\n", $_SESSION['firstName'], $_SESSION['userID']);

$_SESSION['time'] = time();

var_dump($_SESSION);
    </pre>

    <hr/>

    <p>
      <strong>Here are the results:</strong>

    <div>
      <?php
      ini_set('display_errors', true);

      require 'CouchSessionStore.php';
      require_once '../../src/Sag.php';

      $sag = new Sag();
      $sag->login('admin', 'password');
      CouchSessionStore::setSag($sag);

      session_start();
      var_dump($_SESSION);
      echo "<br/>";

      if(!$_SESSION['userID'])
      {
        //Get user's info from the database ... or just hardcode it.
        $_SESSION['userID'] = 100;
        $_SESSION['firstName'] = "Sam";
      }

      printf("Welcome back %s (#%s)!<br/>\n", $_SESSION['firstName'], $_SESSION['userID']);

      $_SESSION['time'] = time();

      var_dump($_SESSION);
      ?>
    </div>
  </body>
</html>
