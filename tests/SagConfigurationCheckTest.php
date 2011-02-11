<?php
/*
   Copyright 2010 Sam Bisbee & Simeon F. Willbanks

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

// See the README in tests/ for information on running and writing these tests.

require_once('PHPUnit/Framework.php');

class SagConfigurationCheckTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
      PHPUnit_Framework_Error_Notice::$enabled = TRUE;
    }

    public function testErrorReportingOff()
    {
      // Turn off all error reporting
      error_reporting(0);
      // SagConfigurationCheck::run() does not generate a PHP Notice!
      require_once('../src/Sag.php');
      $this->assertTrue(class_exists('Sag'));
    }
    
    public function testErrorReportingEErrorOrEWarningOrEParse()
    {
      // Reporting E_NOTICE can be good too (to report uninitialized
      // variables or catch variable name misspellings ...)
      error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
      // SagConfigurationCheck::run() does not generate a PHP Notice!
      require_once('../src/Sag.php');
      $this->assertTrue(class_exists('Sag'));
    }
    
    public function testErrorReportingEAllXorENotice()
    {
      // Report all errors except E_NOTICE
      // This is the default value set in php.ini
      error_reporting(E_ALL ^ E_NOTICE);
      // SagConfigurationCheck::run() does not generate a PHP Notice!
      require_once('../src/Sag.php');
      $this->assertTrue(class_exists('Sag'));
    }
    
    public function testErrorReportingEStrict()
    {
      // Enable to have PHP suggest changes to your code which will ensure the 
      // best interoperability and forward compatibility of your code.
      error_reporting(E_STRICT);
      // SagConfigurationCheck::run() does not generate a PHP Notice!
      require_once('../src/Sag.php');
      $this->assertTrue(class_exists('Sag'));
    }
    
    public function testErrorReportingEAll()
    {
      $this->setExpectedException('PHPUnit_Framework_Error_Notice'); 
      // Report all PHP errors
      error_reporting(E_ALL);
      require_once('../src/Sag.php');
    }
    
    public function testErrorReportingNegative1()
    {
      $this->setExpectedException('PHPUnit_Framework_Error_Notice'); 
      // Report all PHP errors
      error_reporting(-1);
      require_once('../src/Sag.php');
    }
}