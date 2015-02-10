<?php


if (!defined('MOODLE_INTERNAL')) { die('Direct access to this script is forbidden.'); }

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $settings->add(new admin_setting_configtext('domoscio/domoscio_url', get_string('settings_url', 'domoscio'),
        get_string('settings_url_helptext', 'domoscio'), ''));
    
    $settings->add(new admin_setting_configpasswordunmask('domoscio/domoscio_apikey', get_string('settings_apikey', 'domoscio'),
        get_string('settings_apikey_helptext', 'domoscio'), ''));
}


?>