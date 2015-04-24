<?php


if (!defined('MOODLE_INTERNAL')) { die('Direct access to this script is forbidden.'); }

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $settings->add(new admin_setting_configtext('domoscio/domoscio_id', get_string('settings_id', 'domoscio'),
        get_string('settings_id_helptext', 'domoscio'), ''));
    
    $settings->add(new admin_setting_configpasswordunmask('domoscio/domoscio_apikey', get_string('settings_apikey', 'domoscio'),
        get_string('settings_apikey_helptext', 'domoscio'), ''));
}


?>