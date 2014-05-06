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
require_once('Sag.php');
require_once('SagUserUtils.php');

class SagUserUtilsTest extends PHPUnit_Framework_TestCase {
  protected $couchIP;
  protected $couchDBName;
  protected $couchAdminName;
  protected $couchAdminPass;
  protected $couchHTTPAdapter;
  protected $couchSSL;

  protected $couch;

  public function setUp() {
    $this->couchIP = ($GLOBALS['host']) ? $GLOBALS['host'] : '127.0.0.1';
    $this->couchPort = ($GLOBALS['port']) ? $GLOBALS['port'] : '5984';
    $this->couchAdminName = ($GLOBALS['adminName']) ? $GLOBALS['adminName'] : 'admin';
    $this->couchAdminPass = ($GLOBALS['adminPass']) ? $GLOBALS['adminPass'] : 'passwd';
    $this->couchHTTPAdapter = $GLOBALS['httpAdapter'];
    $this->couchSSL = (isset($GLOBALS['ssl'])) ? $GLOBALS['ssl'] : false;

    $this->couch = new Sag($this->couchIP, $this->couchPort);
    $this->couch->setHTTPAdapter($this->couchHTTPAdapter);
    $this->couch->useSSL($this->couchSSL);
    $this->couch->login($this->couchAdminName, $this->couchAdminPass);
    $this->couch->setRWTimeout(5);

    $this->sessionCouch = new Sag($this->couchIP, $this->couchPort);
    $this->couch->setHTTPAdapter($this->couchHTTPAdapter);
    $this->couch->useSSL($this->couchSSL);
    $this->couch->setRWTimeout(5);
  }

  public function test_constructor() {
    $uUtils = new SagUserUtils($this->couch);
    $this->assertInstanceOf('SagUserUtils', $uUtils);

    try {
      new SagUserUtils(null);
      $this->assertTrue(false, 'Should have thrown an exception'); //shouldn't get here
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }

    try {
      new SagUserUtils(new stdClass());
      $this->assertTrue(false, 'Should have thrown an exception'); //shouldn't get here
    }
    catch(SagException $e) {
      $this->assertTrue(true);
    }
  }
    
  public function test_getNonExistingUser() {
    $uUtils = new SagUserUtils($this->couch);

    try {
      $uUtils->getUser('doesnotexist');
      $this->assertTrue(false, 'Should not get here - previous line should throw');
    }
    catch(SagCouchException $e) {
      $this->assertEquals(404, $e->getCode(), 'Expected error code');
    }
  }

  public function test_userLifecycle() {
    $uUtils = new SagUserUtils($this->couch);
    $user = 'bob-sag-et';
    $pass = 'emptyhouse';
    $passChanged = 'fullhouse';

    //Create the user, hoping no one picked our bob-sag-et user before
    $bob = $uUtils->createUser($user, $pass);
    $this->assertEquals(201, $bob->headers->_HTTP->status, 'User created');
    $this->assertEquals('org.couchdb.user:' . $user, $bob->body->id);

    //This causes a server call, so we're validating the password was set
    $session = $this->sessionCouch->login($user, $pass, Sag::$AUTH_COOKIE);
    $this->assertTrue(is_string($session), 'Got a key back');

    //Change the password
    $bob = $uUtils->changePassword($uUtils->getUser($user), $passChanged);
    $this->assertEquals('201', $bob->headers->_HTTP->status, 'proper code');

    //Log back in with the new password
    $session = $this->sessionCouch->login($user, $passChanged, Sag::$AUTH_COOKIE);
    $this->assertTrue(is_string($session), 'Got a key back');

    //Clean up, deleting the user
    $bob = $uUtils->deleteUser($user);
    $this->assertTrue($bob->body->ok, 'ok');
  }
}
