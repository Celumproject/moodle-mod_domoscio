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
 * This view displays student's results according to the Domoscio's API datas
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(dirname(dirname(__FILE__))).'/calendar/lib.php');
require_once(dirname(dirname(__FILE__)).'/scorm/locallib.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/classes/renderer.php');
require_once($CFG->dirroot.'/mod/lesson/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/previewlib.php');

require_login();
require_sesskey();

$config = get_config('domoscio');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$q = optional_param('q', 0, PARAM_INT); // Course_module ID, or
$kn = optional_param('kn', 0, PARAM_INT);
$scorm = optional_param('scorm', '', PARAM_INT);
$end = optional_param('end', false, PARAM_INT);

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
}

$rest = new mod_domoscio_client();
$cache = cache::make_from_params(cache_store::MODE_SESSION, 'mod_domoscio', 'cache');
$tsobj = $cache->get('test_session');

$context = context_course::instance($course->id);
$PAGE->set_context($context);
$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/results.php', array('id' => $id, 'kn' => $kn));
$PAGE->navbar->add($strname);
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_pagelayout('standard');
$PAGE->blocks->show_only_fake_blocks();
$output = $PAGE->get_renderer('mod_domoscio');
$navbc = $tsobj->get_navigation_panel($output, $kn, $id, $end);
$regions = $PAGE->blocks->get_regions();
$PAGE->blocks->add_fake_block($navbc, reset($regions));

$urlresults = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");
$urlresults->param('sesskey', sesskey());
$urlresults->param('id', $id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('results', 'domoscio'));
domoscio_check_settings($config);

if (has_capability('mod/domoscio:submit', $context)) {
    unset($cache->selected);

    if (data_submitted() && confirm_sesskey() && $end == false) {
        // Processing student answer and display correction if available
        $test = $tsobj->get_test_by_kn($kn);
        $display = $test->process_result();

        if ($display != null) {
            echo $display;
        }

        // Result recording and confirmation display
        if ($q || $scorm) {
            // Display an alert message about student Success
            if ($test->get_result()->score == 100) {
                echo html_writer::tag('div', "<i class='icon-ok'></i> ".get_string('test_succeeded', 'domoscio'), array('class' => 'alert alert-success'));
            } else {
                echo html_writer::tag('div', "<i class='icon-remove'></i> ".get_string('test_failed', 'domoscio'), array('class' => 'alert alert-error'));
            }

            // Write json, send it and retrive result from API
            $knstudent = $DB->get_record('domoscio_knode_students', array('userid' => $USER->id,
                                                                         'knodeid' => $kn), '*');
            $json = json_encode(array('knowledge_node_student_id' => intval($knstudent->knodestudentid),
                                                        'payload' => $test->get_result()->score));

            $cache->results[] = json_decode($rest->seturl($config, 'events', null, null, "&type=EventResult")->post($json));

            // Put a new event in calendar
            $knstudent = json_decode($rest->seturl($config, 'knowledge_node_students', $knstudent->knodestudentid)->get());
            $resource = json_decode($rest->seturl($config, 'knowledge_nodes', $kn)->get());
            $newevent = domoscio_create_event($domoscio, $knstudent, $kn, $resource);
        }

        // Remove last test from todolist
        $tsobj->update_todo($kn);
    }

    if ($end == true) {
        $tsobj->clear_todo();
        // Once the todo list is empty, sum up all results for tests done
        echo $tsobj->display_session_results();

        echo html_writer::tag('button', get_string('back_to_coursepage', 'domoscio'), array('type' => 'button',
                                                                               'onclick' => "javascript:location.href='$CFG->wwwroot/course/view.php?id=".$course->id."'"));

        $cache->delete('test_session');
    }

    // If tests remains to be completed
    $gettodo = $tsobj->get_todo();
    if (!empty($gettodo)) {
        // Output Next button
        $todo = $tsobj->get_todo();
        $nextkn = reset($todo)->kn;
        echo html_writer::tag('button',
                              get_string('next_btn', 'domoscio'),
                              array('type' => 'button',
                                 'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".$nextkn."'"));
        $cache->set('test_session', $tsobj);

    } else if (empty($gettodo) && $end == false) {
        // Else, this is the end of the session, display End button
        $urlresults->param('end', true);
        echo $OUTPUT->single_button($urlresults, get_string('results', 'domoscio'));
        $cache->set('test_session', $tsobj);
    }
}
echo $OUTPUT->footer();
