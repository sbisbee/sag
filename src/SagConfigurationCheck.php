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

/**
 * Check current error_reporting value to make sure it jives with Sag.
 * If an error_reporting value is found which generates PHP Notices, trigger
 * a user-level notice message to inform the client of Sag's preferred
 * configuration.
 *
 * @version 0.6.0
 * @package Core
 */
class SagConfigurationCheck {
  public static function run() {
    // This is the default value set in php.ini and Sags preferred value
    $sag_supported = E_ALL ^ E_NOTICE;
    $current = ini_get('error_reporting');

    if ($current > $sag_supported || $current < 0) {
      $notice = "With the current error_reporting value, Sag will generate PHP Notices.";
      trigger_error($notice, E_USER_NOTICE);
    }
  }
}
