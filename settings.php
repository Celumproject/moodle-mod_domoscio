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
 * Domoscio plugin settings
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $settings->add(new admin_setting_configtext('domoscio/domoscio_id', get_string('settings_id', 'domoscio'),
        get_string('settings_id_helptext', 'domoscio'), ''));

    $settings->add(new admin_setting_configpasswordunmask('domoscio/domoscio_apikey', get_string('settings_apikey', 'domoscio'),
        get_string('settings_apikey_helptext', 'domoscio'), ''));

    $settings->add(new admin_setting_configtext('domoscio/domoscio_apiurl', get_string('settings_apiurl', 'domoscio'),
            get_string('settings_apiurl_helptext', 'domoscio'), ''));
}
