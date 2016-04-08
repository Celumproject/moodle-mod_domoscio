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
 * This view call tests for the student
 *
 * If linked exercise is SCORM package, the SCORM player is called.
 * Else if Quiz questions, Quiz interface is called.
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');
require_once(dirname(__FILE__).'/classes/renderer.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot.'/mod/lesson/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/previewlib.php');

$config = get_config('domoscio');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$kn = optional_param('kn', 0, PARAM_INT); // Knowledge_node ID (Rappels)
$solo = optional_param('solo', false, PARAM_BOOL); // If testing one notion
$alert = optional_param('alert', false, PARAM_BOOL); // If testing one notion
$t = optional_param('t', false, PARAM_BOOL); // Start test timestamp
$first = optional_param('first', 0, PARAM_INT); // If initial test (no history)

require_login();

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
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
$context = context_course::instance($course->id);
$PAGE->set_context($context);

$cache = cache::make_from_params(cache_store::MODE_SESSION, 'mod_domoscio', 'cache');

// If test on one notion, preapre new test session
if ($solo == true) {
    $tsobj = new mod_domoscio_test_session();
    $tsobj->fetch_test_by_kn($kn);
    $cache->set('test_session', $tsobj);
    redirect("$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".$kn."&t=true");
    exit;
} else {
    $tsobj = $cache->get('test_session');
}

if ($t == true) {
    $tsobj->start_session();
    $cache->set('test_session', $tsobj);
}

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/doquiz.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_pagelayout('standard');
$PAGE->blocks->show_only_fake_blocks();

$output = $PAGE->get_renderer('mod_domoscio');
$navbc = $tsobj->get_navigation_panel($output, $kn, $id);
$regions = $PAGE->blocks->get_regions();
$PAGE->blocks->add_fake_block($navbc, reset($regions));

$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_title(get_string('test_session', 'domoscio'));
$PAGE->requires->js('/mod/domoscio/script.js', true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('test_session', 'domoscio'));
domoscio_check_settings($config);

if ($alert == true) {
    echo html_writer::tag('div', get_string('scorm_warning', 'domoscio'), array('class' => 'notifyproblem'));
}

if (has_capability('mod/domoscio:submit', $context)) {
    $test = $tsobj->get_test_by_kn($kn);
    if (!empty($test)) {
        echo $test->render_test();
    } else {
        echo get_string('tests_empty', 'domoscio');
    }
}

echo $OUTPUT->footer();
