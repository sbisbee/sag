#!/usr/bin/php
<?php
session_start();

if(!$_SESSION['userID'])
{
  //Get user's info from the database ... or just hardcode it.
  $_SESSION['userID'] = 100;
  $_SESSION['firstName'] = "Sam";
}

printf("Welcome back %s (#%s)!\n", $_SESSION['firstName'], $_SESSION['userID']);
?>
