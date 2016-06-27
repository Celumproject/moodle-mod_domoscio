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
 * Confirmation view when a course creator want to destroy a notion
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');

$config = get_config('domoscio');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('d', 0, PARAM_INT);  // ... domoscio instance ID - it should be named as the first character of the module.
$kn = optional_param('kn', 0, PARAM_INT); // Knowledge_node ID (Rappels)
$confirm = optional_param('confirm', '', PARAM_ALPHANUM); // Confirmation of deletion

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $domoscio  = $DB->get_record('domoscio', array('id' => $n), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = get_coursemodule_from_instance('domoscio', $domoscio->id, $course->id, false, MUST_EXIST);
} else if ($kn) {
    $module     = $DB->get_record('modules', array('name' => 'domoscio'), '*', MUST_EXIST);
    $instance   = $DB->get_record('domoscio_knowledge_nodes', array('knodeid' => $kn), '*', MUST_EXIST);
    $domoscio   = $DB->get_record('domoscio', array('id' => $instance->instance), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = $DB->get_record('course_modules', array('instance' => $domoscio->id, 'module' => $module->id), '*', MUST_EXIST);
    $id         = $cm->id;
} else {
    print_error('missingparameter');
}

$context = context_module::instance($cm->id);
require_login($course, true, $cm);

// Print the page header.
$PAGE->set_url('/mod/domoscio/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($domoscio->name));
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('warning', 'domoscio'));
domoscio_check_settings($config);

$returnurl = new moodle_url('select_notions.php');

$rest = new mod_domoscio_client();

if (has_capability('mod/domoscio:addinstance', $context) && confirm_sesskey()) {

    if ($confirm != md5($kn)) {
        $options = array('kn' => $kn, 'confirm' => md5($kn), 'sesskey' => sesskey());
        $deleteurl = new moodle_url('delete_notion.php', $options);
        $deletebtn = new single_button($deleteurl, get_string('delete'), 'post');

        echo $OUTPUT->confirm(get_string('confirm_notiondel', 'domoscio'), $deletebtn, $deleteurl);

    } else if ($confirm == md5($kn) && data_submitted()) {

        $deletenotion = $rest->seturl($config, 'knowledge_nodes', $kn)->delete();

        $deletedb = $DB->delete_records('domoscio_knowledge_nodes', array('knodeid' => $kn));

        print_string('notion_deleted', 'domoscio');
        echo "<hr/>".html_writer::link(new moodle_url($returnurl, array('id' => $cm->id)),
                                        '<< '.get_string('back_btn', 'domoscio')."&nbsp");
    }

}

// Finish the page.
echo $OUTPUT->footer();
