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
 * Back-end code for handling data about quizzes and the current user's attempt.
 *
 * There are classes for loading all the information about a quiz and attempts,
 * and for displaying the navigation panel.
 *
 * @package   mod_domoscio
 * @copyright 2016 Domoscio SA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/mod/lesson/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/previewlib.php');
require_once(dirname(__FILE__).'/../sdk/client.php');

defined('MOODLE_INTERNAL') || die();
define('QUESTION_PREVIEW_MAX_VARIANTS', 100);


/**
 * This class hold data for a test session
 *
 * @copyright  2016 Domoscio SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_domoscio_test_session {
    protected $list;
    protected $todo;
    protected $timestart;

    public function __construct() {
        $this->list = array();
        $this->timestart = null;
    }

    public function fetch_all_user_tests() {
        global $DB, $USER, $CFG;
        $rest = new mod_domoscio_client();

        $dbknstudents = $DB->get_records("domoscio_knode_students", array("userid" => $USER->id), '', '*');
        $knstudents = json_decode($rest->setUrl(get_config('domoscio'), 'students', array_shift($dbknstudents)->userapiid, 'knowledge_node_students')->get());
        $list = array();

        foreach($knstudents as $knstudent)
        {
            if(strtotime($knstudent->next_review_at) < time() && $knstudent->next_review_at != null && $knstudent->active == 'true')
            {
                $obj = new stdClass;
                $obj->kn = $knstudent->knowledge_node_id;
                $domoscioinstance = $DB->get_record('domoscio_knowledge_nodes', array('knodeid' => $obj->kn));
                try {
                    $obj->domoscio = get_coursemodule_from_instance('domoscio', $domoscioinstance->instance, 0, false, MUST_EXIST);
                    $list[] = $obj;
                } catch (Exception $e) {}
            }
        }

        $this->todo = $list;
        foreach ($this->todo as $obj) {
            $this->list[] = new mod_domoscio_test($obj->kn, $obj->domoscio);
        }
    }

    public function fetch_tests_by_instance($domoscio) {
        global $DB, $CFG;
        $knowledgenodes = $DB->get_records_select('domoscio_knowledge_nodes', "instance = :instance AND active <> 0", array('instance' => $domoscio->id));

        foreach ($knowledgenodes as $kn) {
            $obj = new stdClass;
            $obj->kn = $kn->knodeid;
            $obj->domoscio =  get_coursemodule_from_instance('domoscio', $kn->instance, 0, false, MUST_EXIST);
            $this->todo[] = $obj;
            $this->list[] = new mod_domoscio_test($obj->kn, $obj->domoscio);
        }
    }

    public function fetch_todo_tests_by_instance($domoscio) {
        global $DB, $USER, $CFG;
        $knodestudents = $DB->get_records('domoscio_knode_students', array('instance' => $domoscio->id, 'userid' => $USER->id));

        foreach ($knodestudents as $kns) {
            $rest = new mod_domoscio_client();
            $result = json_decode($rest->seturl(get_config('domoscio'), 'knowledge_node_students', $kns->knodestudentid)->get());

            if ((strtotime($result->next_review_at) < time() && $result->next_review_at != null) || $result->history == null) {
                $obj = new stdClass;
                $obj->kn = $kns->knodeid;
                $obj->domoscio =  get_coursemodule_from_instance('domoscio', $kns->instance, 0, false, MUST_EXIST);
                $this->todo[] = $obj;
                $this->list[] = new mod_domoscio_test($obj->kn, $obj->domoscio);
            }
        }
    }

    public function fetch_test_by_kn($kn) {
        global $DB;
        $obj = new stdClass;
        $obj->kn = $kn;
        $domoscioinstance = $DB->get_record('domoscio_knowledge_nodes', array('knodeid' => $obj->kn))->instance;
        $obj->domoscio =  get_coursemodule_from_instance('domoscio', $domoscioinstance, 0, false, MUST_EXIST);
        $this->todo[] = $obj;
        $this->list[] = new mod_domoscio_test($obj->kn, $obj->domoscio);
    }

    public function get_list() {
        return $this->list;
    }

    public function get_test_by_kn($kn) {
        foreach ($this->list as $test) {
            if ($test->get_related_kn() == $kn) {
                return $test->get_test();
            }
        }
    }

    public function get_todo() {
        return $this->todo;
    }

    public function get_timestart() {
        return $this->timestart;
    }

    public function update_todo($kn) {
        foreach ($this->todo as $k => $todo) {
            if ($kn == $todo->kn) {
                unset($this->todo[$k]);
            }
        }
    }

    public function clear_todo() {
        $this->todo = array();
    }

    public function start_session() {
        $this->timestart = time();
    }

    public function get_navigation_panel(mod_domoscio_renderer $output, $page, $id, $end = false) {
        $bc = new block_contents();
        $bc->attributes['id'] = 'mod_domoscio_navigation_panel';
        $bc->title = get_string('testnav', 'domoscio');
        $bc->content = $output->navigation_panel($this, $page, $id, $end);
        return $bc;
    }

    public function display_session_results() {
        global $OUTPUT;
        $table = new html_table;
        $table->head = array();
        $table->attributes['class'] = 'table table-bordered table-hover';
        $table->head[] = get_string('resourceset_resource', 'domoscio');
        $table->head[] = get_string('score', 'domoscio');
        $table->head[] = get_string('state', 'domoscio');

        $finish = time();
        $runningtime = $finish - $this->timestart;

        foreach ($this->list as $test) {
            $row = new html_table_row();

            if ($test->get_test()->get_result() != null) {

                if ($test->get_test()->get_result()->score == 100) {
                  $state = get_string('notion_ok', 'domoscio');
                  $class = "success";
                  $feedbackclass = "correct";
                } else {
                  $state = html_writer::link($test->get_kn_data()->url, "<i class='icon-book'></i>".get_string('notion_rvw', 'domoscio'), array('target' => '_blank'));
                  $class = "error";
                  $feedbackclass = "incorrect";
                }

                $attributes = array(
                  'src' => $OUTPUT->pix_url('i/grade_' . $feedbackclass),
                  'alt' => get_string($feedbackclass, 'question'),
                  'class' => 'questioncorrectnessicon',
                );

                $row->cells[] = $test->get_kn_data()->display." - ".$test->get_item()->name;
                $row->cells[] = $test->get_test()->get_result()->score;
                $row->cells[] = html_writer::empty_tag('img', $attributes)." ".$state;
                $row->attributes['class'] = $class;

                $table->data[] = $row;
            }
        }

        // Display test session duration
        $output = html_writer::tag('div',
                              html_writer::tag('h5',
                                               get_string('running_time', 'domoscio').date('i:s', $runningtime),
                                               array('class' => 'mod_introbox')),
                              array('class' => 'block'));
        // Display tests table
        $output .= html_writer::table($table);

        return $output;
    }

}


class mod_domoscio_test {
    protected $kn;
    protected $kndata;
    protected $item;
    protected $question;
    protected $test;
    protected $domoscio;

    public function __construct($kn, $domoscio) {
        global $DB, $COURSE;
        $rest = new mod_domoscio_client();
        $config = get_config('domoscio');

        $this->kn = $kn;
        $this->kndata = domoscio_get_resource_info($kn);
        $this->item = json_decode($rest->seturl($config, 'knowledge_nodes', $kn)->get());
        $this->result = null;
        $this->domoscio = $domoscio;

        $selectedquestions = $DB->get_records('domoscio_knode_questions', array('instance' => $domoscio->instance, 'knodeid' => $kn), '', '*');

        if (!empty($selectedquestions)) {
            $random = array_rand($selectedquestions, 1);
            $this->question = $DB->get_record('domoscio_knode_questions', array('id' => $random), '*');
            switch ($this->question->type) {
                case "quiz":
                    $this->test = new mod_domoscio_quiz($this->question, $kn);
                    break;

                case "scorm":
                    $this->test = new mod_domoscio_scorm($this->question, $kn);
                    break;

                case "lesson":
                    $this->test = new mod_domoscio_lesson($this->question, $kn, $domoscio);
                    break;

                case "file":
                    $this->test = new mod_domoscio_fileactivity($this->question, $kn, $domoscio);
                    break;

            }
        }
    }

    public function get_related_kn() {
        return $this->kn;
    }

    public function get_kn_data() {
        return $this->kndata;
    }

    public function get_item() {
        return $this->item;
    }

    public function get_selected_question() {
        return $this->question;
    }

    public function get_test() {
        return $this->test;
    }

    public function get_related_domoscio() {
        return $this->domoscio;
    }
}

class mod_domoscio_quiz {
    protected $question;
    protected $frombank;
    protected $slot;
    protected $cm;
    protected $kn;
    protected $requiredcap;
    protected $result;
    protected $output;

    public function __construct($q, $kn) {
        global $DB, $USER, $CFG;

        if ($this->slot = $DB->get_record('quiz_slots', array('questionid' => $q->questionid), '*')) {
            $this->cm = get_coursemodule_from_instance('quiz', $this->slot->quizid, 0, false, MUST_EXIST);
            $this->frombank = false;
        } else {
            $this->frombank = true;
        }

        $this->question = $q;
        $this->kn = $kn;
        $this->requiredcap = 'mod/quiz:view';
    }

    public function get_result() {
        return $this->result;
    }

    public function process_result() {
        global $DB;

        // Retrive answered question data
        $q = optional_param('q', 0, PARAM_INT); // Course_module ID, or
        $usageid = optional_param('usageid', '', PARAM_INT);
        $slots = optional_param('slots', '', PARAM_INT);

        $selected = $DB->get_record('domoscio_knode_questions', array('questionid' => $q, 'knodeid' => $this->kn), '*');
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
        $this->result = new stdClass;
        $this->result->score = round($quba->get_total_mark() * 100);

        // Display question correction
        return $quba->render_question($slots, $options, $q);
    }

    public function render_test() {
        global $CFG, $DB, $USER;

        $urlresults = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");
        $urlresults->param('sesskey', sesskey());

        $hascap = false;
        // If question related to a quiz module, check if student can view that module
        if ($this->cm) {
            $cmcontext = context_module::instance($this->cm->id);
            if (has_capability($this->requiredcap, $cmcontext)) {
                $hascap = true;
            }
        }
        $output = "";

        // Check for student capability or if question from plugin bank
        if (($hascap == true) || ($this->frombank == true)) {
            $question = $DB->get_record('question', array('id' => $this->question->questionid), '*');
            $qinstance = "kn_q".$question->id;

            $urlresults->param('kn', $this->kn);
            $urlresults->param('q', $question->id);
            $actionurl = $urlresults;

            $q = question_bank::load_question($this->question->questionid);

            $quba = question_engine::make_questions_usage_by_activity(
                    'mod_domoscio', context_user::instance($USER->id));
            $options = new question_preview_options($question);
            $quba->set_preferred_behaviour('deferredfeedback');
            $slot = $quba->add_question($q, $options->maxmark);
            $displaynumber = $question->id;
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
            $output .= html_writer::start_tag('form', array('method' => 'post', 'action' => $actionurl,
                    'enctype' => 'multipart/form-data', 'id' => 'responseform'));
            $output .= html_writer::start_tag('div');
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'usageid', 'value' => $usageid));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots', 'value' => $slot));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'scrollpos', 'value' => '', 'id' => 'scrollpos'));
            $output .= html_writer::end_tag('div');

            $output .= $quba->render_question($slot, $options, $displaynumber);

            // Finish the question form.
            $output .= html_writer::start_tag('div', array('id' => 'previewcontrols', 'class' => 'controls'));
            $output .= html_writer::empty_tag('input', array('type' => 'submit',
                                                       'name' => 'save',
                                                      'value' => get_string('save', 'question')));
            $output .= html_writer::end_tag('div');
            $output .= html_writer::end_tag('form');
        } else {
            $output .= get_string('nocapabilitytousethisservice', 'error');
        }
        $this->output = $output;

        return $this->output;
    }
}

class mod_domoscio_scorm {
    protected $sco;
    protected $cm;
    protected $kn;
    protected $requiredcap;
    protected $result;
    protected $output;

    public function __construct($q, $kn) {
        global $DB;

        $this->sco = $DB->get_record('scorm_scoes', array('id' => $q->questionid), '*');
        $this->cm = get_coursemodule_from_instance('scorm', $this->sco->scorm, 0, false, MUST_EXIST);
        $this->kn = $kn;
        $this->requiredcap = 'mod/scorm:savetrack';
    }

    public function get_result() {
        return $this->result;
    }

    public function process_result() {
        global $CFG, $USER, $DB;

        if ($tracks = scorm_get_tracks($this->sco->id, $USER->id)) {
            if (isset($tracks->{"cmi.score.scaled"})) {
                $score = $tracks->{"cmi.score.scaled"};
            } else if (isset($tracks->{"cmi.core.score.raw"})) {
                $scoreraw = $tracks->{"cmi.core.score.raw"};
                $scoremax = $tracks->{"cmi.core.score.max"};
                $score = $scoreraw / $scoremax;
            } else {
                // If student didn't answered, reload doquiz page
                redirect("$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".$this->kn."&alert=1");
                exit;
            }
            // Scale the result to be used by Domoscio API
            if (isset($score) && confirm_sesskey()) {
                $this->result = new stdClass;
                $this->result->score = $score * 100;
            }
        }
    }

    public function render_test() {
        global $DB, $CFG;

        $urlresults = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");
        $urlresults->param('sesskey', sesskey());
        $alert = optional_param('alert', 0, PARAM_INT);
        $cmcontext = context_module::instance($this->cm->id);
        $output = "";

        if (has_capability($this->requiredcap, $cmcontext)) {
            $scoid = $this->sco->id;
            $content = "<input type='hidden' value=$scoid name=scoid></input>";
            $scourl = "$CFG->wwwroot/mod/scorm/player.php?a=".$this->cm->instance."&scoid=$scoid&newattempt=on&display=popup";

            $scormframe = html_writer::tag('iframe', '', array('src' => $scourl,
                                                                'width' => 1000,
                                                                'height' => 500,
                                                                'style' => 'border:none'));

            $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next'));

            $urlresults->param('scorm', $this->cm->instance);
            $urlresults->param('kn', $this->kn);

            $output = $scormframe.html_writer::tag('form', $content, array('method' => 'POST', 'action' => $urlresults, 'id' => 'responseform'));

            if ($alert > 0) {
                $output .= html_writer::tag('div', get_string('scorm_warning', 'domoscio'), array('class' => 'notifyproblem'));
            }


        } else {
            $output .= get_string('nocapabilitytousethisservice', 'error');
        }

        $this->output = $output;
        return $this->output;
    }
}

class mod_domoscio_lesson {
    protected $lessonpage;
    protected $lesson;
    protected $result;
    protected $output;
    protected $domoscio;

    public function __construct($q, $kn, $domoscio) {
        global $DB;

        $this->question = $q;
        $this->kn = $kn;
        $this->domoscio = $domoscio;
        $this->lessonpage = $DB->get_record('lesson_pages', array('id' => $q->questionid), '*');
        $this->lesson = new lesson($DB->get_record('lesson', array('id' => $this->lessonpage->lessonid), '*', MUST_EXIST));
    }

    public function get_result() {
        return $this->result;
    }

    public function process_result() {
        global $DB;
        $q = optional_param('q', 0, PARAM_INT); // Course_module ID, or

        $question = $DB->get_record('lesson_pages', array('id' => $q), '*');
        $lesson = new lesson($DB->get_record('lesson', array('id' => $question->lessonid), '*', MUST_EXIST));
        $page = $lesson->load_page($q);

        // Retrieve question type
        $qtype = $page->get_idstring();
        $submitted = optional_param('q0:'.$question->id.'_answer', '', PARAM_TEXT);

        if ($qtype == "calculated" || $qtype == "numerical" || $qtype == "shortanswer") {
            $this->result = domoscio_get_input_result($question, $submitted);
        } else if ($qtype == "multichoice" || $qtype == "calculatedmulti" || $qtype == "truefalse") {
            $this->result = domoscio_get_multi_choice_result($question, $submitted);
        } else if ($qtype == "multianswer") {
            $this->result = domoscio_get_multi_result($question, $submitted);
        } else if ($qtype == "matching") {
            $this->result = domoscio_get_match_result($question);
        }

        // Display correction
        $qspan = html_writer::start_span('qno') . $question->id . html_writer::end_span();
        $qheader = html_writer::tag('h3', get_string('question', 'domoscio').$qspan, array('class' => 'no'));

        $qcontent = html_writer::tag('div', $this->result->output, array('class' => 'formulation'));

        $output = html_writer::tag('div', $qheader, array('class' => 'info'));
        $output .= html_writer::tag('div', $qcontent, array('class' => 'content'));
        $output = html_writer::tag('div', $output, array('class' => 'que '.$qtype.' deferredfeedback notyetanswered'));

        return $output;
    }

    public function render_test() {
        global $DB, $CFG;

        $urlresults = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");
        $urlresults->param('kn', $this->kn);
        $urlresults->param('sesskey', sesskey());
        $urlresults->param('q', $this->question->questionid);

        $output = "";

        if ($this->lesson->is_accessible()) {
            $question = $DB->get_record('lesson_pages', array('id' => $this->question->questionid), '*');
            $page = $this->lesson->load_page($this->question->questionid);

            $qinstance = "kn_q".$question->id;
            $content = html_writer::tag('input', '', array('type' => 'hidden', 'value' => $this->domoscio->id, 'name' => $qinstance))
                      .domoscio_display_questions($question, $page->get_idstring());

            $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next'));
            $output = html_writer::tag('form', $content, array('method' => 'POST', 'action' => $urlresults, 'id' => 'responseform'));

            $this->output = $output;
        } else {
            $output .= get_string('nocapabilitytousethisservice', 'error');
        }

        return $this->output;
    }
}

class mod_domoscio_fileactivity {
    protected $cm;
    protected $question;
    protected $kn;
    protected $file;
    protected $result;

    public function __construct($q, $kn, $domoscio) {
        global $DB;
        $fs = get_file_storage();
        $this->cm = get_coursemodule_from_instance('domoscio', $domoscio->instance, 0, false, MUST_EXIST);
        $cmcontext = context_module::instance($this->cm->id);

        $this->question = $q;
        $this->kn = $kn;
        $fileentry = $DB->get_record('files', array('id' => $q->questionid), '*');
        $this->file = $fs->get_file($cmcontext->id, 'mod_domoscio', 'content', 0, '/Content/', $fileentry->filename);
    }

    public function get_result() {
        return $this->result;
    }

    public function process_result() {
        global $CFG;
        $score = optional_param('score', '', PARAM_ALPHANUM);

        if ($score == "NAN") {
            // If student didn't answered, reload doquiz page
            redirect("$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".$this->kn."&alert=1");
            exit;
        } else {
            $this->result = new stdClass;
            $this->result->score = $score * 10;
        }
    }

    public function render_test() {
        global $CFG;

        $url = moodle_url::make_pluginfile_url($this->file->get_contextid(), $this->file->get_component(), $this->file->get_filearea(), $this->file->get_itemid(), $this->file->get_filepath(), $this->file->get_filename());

        $urlresults = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");
        $urlresults->param('kn', $this->kn);
        $urlresults->param('q', $this->question->questionid);
        $urlresults->param('sesskey', sesskey());

        $frame = html_writer::tag('iframe', "", array('id' => 'iframe', 'src' => $url->out(), 'width' => 800, 'height' => 600));
        $content = html_writer::tag('input', '', array('type' => 'hidden', 'id' => 'scoretag', 'name' => 'score', 'value' => ''));
        $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next', 'onclick' => 'javascript:send_result()'));
        $output = $frame;
        $output .= html_writer::tag('form', $content, array('method' => 'POST', 'action' => $urlresults, 'id' => 'responseform'));

        return $output;
    }
}
