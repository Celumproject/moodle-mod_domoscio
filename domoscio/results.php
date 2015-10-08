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
require_once($CFG->dirroot.'/mod/lesson/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/previewlib.php');

require_login();
require_sesskey();

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$q = optional_param('q', 0, PARAM_INT); // Course_module ID, or
$kn = optional_param('kn', 0, PARAM_INT);
$scorm = optional_param('scorm', '', PARAM_INT);
$end = optional_param('end', false, PARAM_INT);
$usageid = optional_param('usageid', '', PARAM_INT);
$slots = optional_param('slots', '', PARAM_INT);

$config = get_config('domoscio');
$context = context_system::instance();
$PAGE->set_context($context);

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

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_pagelayout('incourse');

$rest = new mod_domoscio_client();
$urlresults = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");
$urlresults->param('sesskey', sesskey());

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('results', 'domoscio'));
if (has_capability('mod/domoscio:submit', $context)) {
    unset($SESSION->selected);
    if ($q) {
        // If exercise is Quiz question or lesson question
        if (data_submitted() && confirm_sesskey()) {
            // Retrive answered question data
            $selected = $DB->get_record('domoscio_knode_questions', array('questionid' => $q, 'knodeid' => $kn), '*');
            if ($selected->type == "quiz") {
                $question = $DB->get_record_sql("SELECT *
                                                   FROM {question}
                                                  WHERE id = :qid",
                                                array('qid' => $q)
                                               );

                $quba = question_engine::load_questions_usage_by_activity($usageid);
                $correctresponse = $quba->get_correct_response($slots);

                if (!is_null($correctresponse)) {
                    $transaction = $DB->start_delegated_transaction();
                    $timenow = time();
                    $quba->process_all_actions($timenow);
                    question_engine::save_questions_usage_by_activity($quba);
                    $transaction->allow_commit();
                    $quba->finish_question($slots, $timenow);
                }

                $options = new question_preview_options($question);
                $options->readonly = true;
                $result = new stdClass;
                $result->score = round($quba->get_total_mark() * 100);

                // Display question correction
                echo $quba->render_question($slots, $options, $q);
            } else if ($selected->type == "lesson") {
                $question = $DB->get_record('lesson_pages', array('id' => $q), '*');
                $lesson = new lesson($DB->get_record('lesson', array('id' => $question->lessonid), '*', MUST_EXIST));
                $page = $lesson->load_page($q);

                // Retrieve question type
                $qtype = $page->get_idstring();
                $submitted = optional_param('q0:'.$question->id.'_answer', '', PARAM_TEXT);

                if ($qtype == "calculated" || $qtype == "numerical" || $qtype == "shortanswer") {
                    $result = domoscio_get_input_result($question, $submitted);
                } else if ($qtype == "multichoice" || $qtype == "calculatedmulti" || $qtype == "truefalse") {
                    $result = domoscio_get_multi_choice_result($question, $submitted);
                } else if ($qtype == "multianswer") {
                    $result = domoscio_get_multi_result($question, $submitted);
                } else if ($qtype == "matching") {
                    $result = domoscio_get_match_result($question);
                }

                // Display correction
                $qspan = html_writer::start_span('qno') . $question->id . html_writer::end_span();
                $qheader = html_writer::tag('h3', get_string('question', 'domoscio').$qspan, array('class' => 'no'));

                $qcontent = html_writer::tag('div', $result->output, array('class' => 'formulation'));

                $output = html_writer::tag('div', $qheader, array('class' => 'info'));
                $output .= html_writer::tag('div', $qcontent, array('class' => 'content'));
                $output = html_writer::tag('div', $output, array('class' => 'que '.$qtype.' deferredfeedback notyetanswered'));

                echo $output;
            }
        }
    } else if ($scorm) {
        // else if exercise is SCO
        if (data_submitted() && confirm_sesskey()) {
            $scoid = optional_param('scoid', null, PARAM_INT);
        }
        $redirect = optional_param('redirect', true, PARAM_BOOL);

        // Redirect once to search for student answer being written in DB
        if ($redirect == true && confirm_sesskey()) {
            if (isset($scoid)) {
                $SESSION->scoid = $scoid;
            }

            $urlresults->param('id', $id);
            $urlresults->param('scorm', $scorm);
            $urlresults->param('kn', $kn);
            $urlresults->param('redirect', false);
            redirect($urlresults);
            exit;
        } else {
            // Once student result is written, retrive the answer
            if ($tracks = scorm_get_tracks($SESSION->scoid, $USER->id)) {
                if (isset($tracks->{"cmi.score.scaled"})) {
                    $score = $tracks->{"cmi.score.scaled"};
                } else if (isset($tracks->{"cmi.core.score.raw"})) {
                    $scoreraw = $tracks->{"cmi.core.score.raw"};
                    $scoremax = $tracks->{"cmi.core.score.max"};
                    $score = $scoreraw / $scoremax;
                } else {
                    // If student didn't answered, reload doquiz page
                    redirect("$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".$kn."&alert=true");
                    exit;
                }
                // Scale the result to be used by Domoscio API
                if (isset($score) && confirm_sesskey()) {
                    $result = new stdClass;
                    $result->score = $score * 100;
                    unset($SESSION->scoid);
                }
            }
        }
    } else if ($end == true) {
        // Once the todo list is empty, sum up all results at tests done
        if (isset($SESSION->start)) {
            $finish = time();
            $runningtime = $finish - $SESSION->start;

            // Display test session duration
            echo html_writer::tag('div',
                                  html_writer::tag('h5',
                                                   get_string('running_time', 'domoscio').date('i:s', $runningtime),
                                                   array('class' => 'mod_introbox')),
                                  array('class' => 'block'));

            unset($SESSION->start);
            $trows = "";

            foreach ($SESSION->results as $rapport) {
                // For each notion, display student result
                $kns = $DB->get_record('domoscio_knode_students', array('knodestudentid' => $rapport->knowledge_node_student_id), '*');

                $resource = domoscio_get_resource_info($kns->knodeid);

                $kninfo = json_decode($rest->seturl($config, 'knowledge_nodes', $kns->knodeid)->get());

                if ($rapport->payload == 100) {
                    $state = get_string('notion_ok', 'domoscio');
                    $class = "success";
                    $feedbackclass = "correct";
                } else {
                    $state = html_writer::link($resource->url, "<i class='icon-book'></i>".get_string('notion_rvw', 'domoscio'), array('target' => '_blank'));
                    $class = "error";
                    $feedbackclass = "incorrect";
                }

                $attributes = array(
                    'src' => $OUTPUT->pix_url('i/grade_' . $feedbackclass),
                    'alt' => get_string($feedbackclass, 'question'),
                    'class' => 'questioncorrectnessicon',
                );

                $trows .= html_writer::tag('tr', html_writer::tag('td', $resource->display." - ".$kninfo->name).
                                                html_writer::tag('td', $rapport->payload).
                                                html_writer::tag('td', html_writer::empty_tag('img', $attributes)." ".$state),
                                                array("class" => $class)
                                           );
            }
            $th = html_writer::tag('tr', html_writer::tag('th', get_string('resourceset_resource', 'domoscio')).
                                         html_writer::tag('th', get_string('score', 'domoscio')).
                                         html_writer::tag('th', get_string('state', 'domoscio')
                                         )
                                  );
            echo html_writer::tag('table', $th.$trows, array('class' => 'table table-bordered table-hover'));

            unset($SESSION->results);
            unset($SESSION->todo);
        }

        echo html_writer::tag('button', get_string('home_btn', 'domoscio'), array('type' => 'button',
                                                                               'onclick' => "javascript:location.href='$CFG->wwwroot/'"));
    }

    if ($end == false) {
        if ($q || $scorm) {
            // Display an alert message about student Success
            if ($result->score == 100) {
                echo html_writer::tag('div', "<i class='icon-ok'></i> ".get_string('test_succeeded', 'domoscio'), array('class' => 'alert alert-success'));
            } else {
                echo html_writer::tag('div', "<i class='icon-remove'></i> ".get_string('test_failed', 'domoscio'), array('class' => 'alert alert-error'));
            }

            // Write json, send it and retrive result from API
            $knstudent = $DB->get_record('domoscio_knode_students', array('userid' => $USER->id,
                                                                         'knodeid' => $kn), '*');
            $json = json_encode(array('knowledge_node_student_id' => intval($knstudent->knodestudentid),
                                                        'payload' => $result->score));

            $SESSION->results[] = json_decode($rest->seturl($config, 'events', null, null, "&type=EventResult")->post($json));

            // Put a new event in calendar
            $knstudent = json_decode($rest->seturl($config, 'knowledge_node_students', $knstudent->knodestudentid)->get());
            $resource = json_decode($rest->seturl($config, 'knowledge_nodes', $kn)->get());

            $newevent = domoscio_create_event($domoscio, $knstudent, $kn, $resource);
        }
    }

    if (!empty($SESSION->todo)) {
        echo html_writer::tag('button',
                              get_string('next_btn', 'domoscio'),
                              array('type' => 'button',
                                 'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".array_shift($SESSION->todo)."'"));
    } else if (empty($SESSION->todo) && $end == false) {
        $urlresults->param('end', true);
        echo $OUTPUT->single_button($urlresults, get_string('end_btn', 'domoscio'));
    }
}

echo $OUTPUT->footer();
