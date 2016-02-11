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
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot.'/mod/lesson/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/previewlib.php');

$config = get_config('domoscio');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$kn = optional_param('kn', 0, PARAM_INT); // Knowledge_node ID (Rappels)
$solo = optional_param('solo', false, PARAM_INT); // Si test unitaire
$alert = optional_param('alert', false, PARAM_INT); // Si test unitaire
$t = optional_param('t', null, PARAM_INT); // Start test timestamp
$first = optional_param('first', 0, PARAM_INT); // If initial test (no history)

if ($solo == 'true') {
    $SESSION->todo = null;
}
if ($t) {
    $SESSION->start = $t;
}
if ($first == 1) {
    $SESSION->todo = $SESSION->no_history;
}

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

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/doquiz.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('test_session', 'domoscio'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('test_session', 'domoscio'));
domoscio_check_settings($config);

if (has_capability('mod/domoscio:submit', $context)) {
    $urlresults = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");
    $urlresultsend = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");

    $urlresults->param('sesskey', sesskey());
    $urlresultsend->param('sesskey', sesskey());

    // Retrive selected questions id
    $lists = $DB->get_records('domoscio_knode_questions', array('instance' => $domoscio->id, 'knodeid' => $kn), '', '*');

    if (!empty($lists)) {
        if (isset($SESSION->selected)) {
            $random = $SESSION->selected;
        } else {
            $random = $SESSION->selected = array_rand($lists, 1);
        }
        $selected = $DB->get_record('domoscio_knode_questions', array('id' => $random), '*');

        if ($selected->type == "scorm") {
            $scorm = $DB->get_record('scorm_scoes', array('id' => $selected->questionid), '*');
            $cmselected = $scorm->scorm;
            $cap = 'mod/scorm:savetrack';
            $module = 18;
        } else if ($selected->type == "quiz") {
            $slot = $DB->get_record('quiz_slots', array('questionid' => $selected->questionid), '*');
            $cmselected = $slot->quizid;
            $cap = 'mod/quiz:view';
            $module = 16;
        } else if ($selected->type == "lesson") {
            $lessonpage = $DB->get_record('lesson_pages', array('id' => $selected->questionid), '*');
            $lesson = new lesson($DB->get_record('lesson', array('id' => $lessonpage->lessonid), '*', MUST_EXIST));
        }

        if ($selected->type !== "lesson") {
            // Check student is allowed to see selected question
            $cmid = $DB->get_record('course_modules', array('instance' => $cmselected, 'module' => $module));
            $cmcontext = context_module::instance($cmid->id);

            if (has_capability($cap, $cmcontext)) {
                if ($selected->type == "scorm") {
                    $domoscioid = $temp = $cm->id;
                    $a = $scorm->scorm;
                    $scoid = $selected->questionid;
                    $content = "<input type='hidden' value=$scoid name=scoid></input>";
                    $scourl = "$CFG->wwwroot/mod/scorm/player.php?a=$scorm->scorm&scoid=$selected->questionid&newattempt=on&display=popup";

                    $scormframe = html_writer::tag('iframe', '', array('src' => $scourl,
                                                                        'width' => 1000,
                                                                        'height' => 500,
                                                                        'style' => 'border:none'));

                    $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next'));

                    $urlresults->param('id', $temp);
                    $urlresults->param('scorm', $a);
                    $urlresults->param('kn', $kn);

                    $output = $scormframe.html_writer::tag('form', $content, array('method' => 'POST', 'action' => $urlresults, 'id' => 'responseform'));

                    if ($alert == 'true') {
                        echo html_writer::tag('div', get_string('scorm_warning', 'domoscio'), array('class' => 'notifyproblem'));
                    }
                    echo $output;
                } else {
                    if ($selected->type == "quiz") {
                        // Retrieve selected question data
                        $question = $DB->get_record('question', array('id' => $selected->questionid), '*');
                    }
                    $qinstance = "kn_q".$question->id;
                    $urlresults->param('kn', $kn);
                    $urlresults->param('q', $selected->questionid);

                    $actionurl = $urlresults;
                    define('QUESTION_PREVIEW_MAX_VARIANTS', 100);
                    $q = question_bank::load_question($selected->questionid);

                    $quba = question_engine::make_questions_usage_by_activity(
                            'mod_domoscio', context_user::instance($USER->id));
                    $options = new question_preview_options($question);
                    $quba->set_preferred_behaviour('deferredfeedback');
                    $slot = $quba->add_question($q, $options->maxmark);
                    $displaynumber = $selected->questionid;
                    $maxvariant = min($q->get_num_variants(), QUESTION_PREVIEW_MAX_VARIANTS);
                    if ($options->variant) {
                        $options->variant = min($maxvariant, max(1, $options->variant));
                    } else {
                        $options->variant = rand(1, $maxvariant);
                    }
                    $quba->start_all_questions();
                    question_engine::save_questions_usage_by_activity($quba);
                    $usageid = $quba->get_id();

                    // Start the question form.
                    echo html_writer::start_tag('form', array('method' => 'post', 'action' => $actionurl,
                            'enctype' => 'multipart/form-data', 'id' => 'responseform'));
                    echo html_writer::start_tag('div');
                    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'usageid', 'value' => $usageid));
                    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
                    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots', 'value' => $slot));
                    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos', 'value' => '', 'id' => 'scrollpos'));
                    echo html_writer::end_tag('div');

                    echo $quba->render_question($slot, $options, $displaynumber);

                    // Finish the question form.
                    echo html_writer::start_tag('div', array('id' => 'previewcontrols', 'class' => 'controls'));
                    echo html_writer::empty_tag('input', array('type' => 'submit',
                                                               'name' => 'save',
                                                              'value' => get_string('save', 'question')));
                    echo html_writer::end_tag('div');
                    echo html_writer::end_tag('form');
                }
            } else {
                echo get_string('nocapabilitytousethisservice', 'error');
            }
        } else if ($lesson->is_accessible()) {
            $question = $DB->get_record('lesson_pages', array('id' => $selected->questionid), '*');
            $page = $lesson->load_page($selected->questionid);

            $qinstance = "kn_q".$question->id;
            $content = html_writer::tag('input', '', array('type' => 'hidden', 'value' => $domoscio->id, 'name' => $qinstance))
                      .domoscio_display_questions($question, $page->get_idstring());
            $urlresults->param('kn', $kn);
            $urlresults->param('q', $selected->questionid);

            $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next'));
            $output = html_writer::tag('form', $content, array('method' => 'POST', 'action' => $urlresults, 'id' => 'responseform'));
            echo $output;

        } else {
            echo get_string('nocapabilitytousethisservice', 'error');
        }
    } else {
        echo html_writer::tag('blockquote', get_string('tests_empty', 'domoscio'), array('class' => 'muted'));
    }
    $urlresultsend->param('end', true);
    echo $OUTPUT->single_button($urlresultsend, get_string('end_btn', 'domoscio'));
}
echo $OUTPUT->footer();