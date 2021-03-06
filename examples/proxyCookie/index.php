<?php
require_once('../../src/Sag.php');

session_start();

try {
  // We are going to get our page's content from Sag.
  $sag = new Sag('sbisbee.com');
  $sag->setDatabase('outlook');

  if($_POST['login']) {
    echo '<p>Using login()';

    $_SESSION['AuthSession'] = $sag->login($_POST['username'], $_POST['password'], $sag::$AUTH_COOKIE);
  }
  else if($_SESSION['AuthSession']) {
    echo '<p>Using setCookie()';

    $sag->setCookie('AuthSession', $_SESSION['AuthSession']);
  }

  $result = $sag->get('/');
}
catch(Exception $e) {
  $error = $e->getMessage();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
   "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8"/>
    <title>proxyCookie example</title>
  </head>
  <body>
    <?php
    echo "<p>$error";

    if($result) {
      //Fancy display code.
      var_dump($result);
    }
    else {
      ?>
      <form method="post" action="./index.php">
        <input type="hidden" name="login" value="1"/>
        <label for="username">Name</label> <input type="text" name="username"/><br/>
        <label for="password">Password</label> <input type="password" name="password"/><br/>
        <input type="submit" value="Login"/>
      </form>
      <?php
    }
    ?>
  </body>
</html>
