<?php
/*
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
require_once('../src/Sag.php');

class SagConfigurationCheckTest extends PHPUnit_Framework_TestCase
{
    public function testErrorReportingOff()
    {
      // Turn off all error reporting
      error_reporting(0);
      $this->assertTrue(class_exists('Sag'));
      // SagConfigurationCheck::run() does not generate a PHP Notice!
      $s = new Sag();
      $this->assertTrue($s instanceOf Sag);
    }
    
    public function testErrorReportingEErrorOrEWarningOrEParse()
    {
      // Reporting E_NOTICE can be good too (to report uninitialized
      // variables or catch variable name misspellings ...)
      error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
      $this->assertTrue(class_exists('Sag'));
      // SagConfigurationCheck::run() does not generate a PHP Notice!
      $s = new Sag();
      $this->assertTrue($s instanceOf Sag);
    }
    
    public function testErrorReportingEAllXorENotice()
    {
      // Report all errors except E_NOTICE
      // This is the default value set in php.ini
      error_reporting(E_ALL ^ E_NOTICE);
      $this->assertTrue(class_exists('Sag'));
      // SagConfigurationCheck::run() does not generate a PHP Notice!
      $s = new Sag();
      $this->assertTrue($s instanceOf Sag);
    }
    
    public function testErrorReportingEStrict()
    {
      // Enable to have PHP suggest changes to your code which will ensure the 
      // best interoperability and forward compatibility of your code.
      error_reporting(E_STRICT);
      $this->assertTrue(class_exists('Sag'));
      // SagConfigurationCheck::run() does not generate a PHP Notice!
      $s = new Sag();
      $this->assertTrue($s instanceOf Sag);
    }
    
    public function testErrorReportingEAll()
    {
      // Report all PHP errors
      error_reporting(E_ALL);
      $this->assertTrue(class_exists('Sag'));
      // SagConfigurationCheck::run() will generate a PHP Notice.
      $this->setExpectedException('PHPUnit_Framework_Error'); 
      $s = new Sag();
      $this->assertTrue($s instanceOf Sag);
    }
    
    public function testErrorReportingNegative1()
    {
      // Report all PHP errors
      error_reporting(-1);
      $this->assertTrue(class_exists('Sag'));
      // SagConfigurationCheck::run() will generate a PHP Notice.
      $this->setExpectedException('PHPUnit_Framework_Error'); 
      $s = new Sag();
      $this->assertTrue($s instanceOf Sag);
    }
}
