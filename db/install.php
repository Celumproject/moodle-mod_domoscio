<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides code to be executed during the module installation
 *
 * This file replaces the legacy STATEMENTS section in db/install.xml,
 * lib.php/modulename_install() post installation hook and partially defaults.php.
 *
 * @package    mod_domoscio
 * @copyright  2016 Domoscio SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../sdk/backend.php');

/**
 * Post installation procedure
 *
 * @see upgrade_plugins_modules()
 */
function xmldb_domoscio_install() {

    global $CFG, $SITE, $USER;

    $json = json_encode(array('user' => array(
                                'email' => $USER->email,
                                'firstname' => $USER->firstname,
                                'sirname' => $USER->lastname,
                                'organization' => strip_tags($SITE->fullname)
                                        ),
                              'instance' => array(
                                'title' => get_site()->fullname,
                                'instance_type_id' => 2,
                                'instance_mode_id' => 1,
                                'url' => $CFG->wwwroot
                                        )
                             )
                       );

    $rest = new mod_domoscio_backend();
    $instanceapi = json_decode($rest->post($json));

    set_config('domoscio_id', $instanceapi->client_id, 'domoscio');
    set_config('domoscio_apikey', $instanceapi->client_passphrase, 'domoscio');
    set_config('domoscio_apiurl', "http://stats-engine.domoscio.com/v1", 'domoscio');

    return true;

}

/**
 * Post installation recovery procedure
 *
 * @see upgrade_plugins_modules()
 */
function xmldb_domoscio_install_recovery() {
}
