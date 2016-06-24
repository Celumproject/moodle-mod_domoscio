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
 * LinkTo view
 *
 * This is the view where an editing teacher can link a freshly created quiz
 * to an existing resource
 *
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/classes/linkto_form.php');
require_once(dirname(dirname(dirname(__FILE__))).'/question/classes/bank/view.php');
require_once(dirname(dirname(dirname(__FILE__))).'/question/category_class.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

$config = get_config('domoscio');
$id = optional_param('cmid', 0, PARAM_INT); // Course_module ID, or
$q = optional_param('q', 0, PARAM_INT);
$kn = optional_param('notion', 0, PARAM_INT);
$exo = optional_param('exo', null, PARAM_ALPHANUMEXT);
$bank = optional_param('bank', false, PARAM_BOOL);
$pagenum = optional_param('pagenum', 1, PARAM_INT); // Activities list pagination

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio   = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    print_error('missingparameter');
}

$context = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);
require_course_login($course);

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title($domoscio->name);
$PAGE->set_heading($course->fullname." > ".$domoscio->name);
$PAGE->set_pagelayout('incourse');

$rest = new mod_domoscio_client();

$resource = json_decode($rest->seturl($config, 'knowledge_nodes', $domoscio->resourceid)->get());
$notion = json_decode($rest->seturl($config, 'knowledge_nodes', $kn)->get());

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('choose_q', 'domoscio'));
domoscio_check_settings($config);

$linkedmodule = domoscio_get_resource_info($resource->id);


/* ----- MOODLE QUIZ QUESTIONS -----*/

if (has_capability('mod/domoscio:addinstance', $context)) {
    $linktourl = new moodle_url("$CFG->wwwroot/mod/domoscio/linkto.php");
    $linktourl->param('cmid', $cm->id);
    $linktourl->param('notion', $kn);

    echo html_writer::tag('div', get_string('linkto_intro', 'domoscio').
    html_writer::tag('b', $linkedmodule->display." - ".$notion->name, array('class' => '')), array('class' => 'well well-small'));
    echo html_writer::link("$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id", '<< '.get_string('back_btn', 'domoscio')."&nbsp");

    if (($exo == null && $bank == true) || ($exo != null)) {

        if ($bank == true) {
            $url = new moodle_url("$CFG->wwwroot/mod/domoscio/linkto.php");
            $url->param('cmid', $id);
            $url->param('notion', $kn);
            $url->param('pagenum', $pagenum);
            $url->param('courseid', $course->id);

            list($thispageurl, $contexts, $cmid, $cm, $module, $pagevars) =
                    question_edit_setup('questions', $url);

            $qf = question_finder::get_instance();
            $cat = $DB->get_record('question_categories', array('contextid' => $context->id), 'id')->id;
            $questionsdata = $qf->get_questions_from_categories($cat, null);
            $qtype = "quiz";
            $exoid = null;
            $linktourl->param('bank', true);
        }

        if ($exo != null) {
            $selected = explode('_', $exo);
            $qtype = $selected[0];

            if ($qtype == 'quiz') {
                $cap = 'mod/quiz:manage';
            } else if ($qtype == 'scorm') {
                $cap = 'mod/scorm:viewreport';
            } else if ($qtype == 'lesson') {
                $cap = 'mod/lesson:manage';
            }
            $module = $DB->get_record('modules', array('name' => $qtype), 'id')->id;;
            $cmid = $DB->get_record('course_modules', array('instance' => $selected[1], 'module' => $module));
            $cmcontext = context_module::instance($cmid->id);

            if (has_capability($cap, $cmcontext)) {
                $linktourl->param('exo', $exo);
            } else {
                print_string('nocapabilitytousethisservice', 'error');
            }

            $questionsdata = null;
            $exoid = $selected[1];

        }

        if (($bank == true && !empty($questionsdata)) || $bank == false) {

            $mform = new mod_domoscio_linkto_form($linktourl,
                array('kn_id' => $kn, 'module' => $qtype, 'exo_id' => $exoid, 'questions' => $questionsdata));

            if ($mform->is_cancelled()) {
                $linktourl->param('exo', null);
                redirect($linktourl);
                exit;
            } else if ($fromform = $mform->get_data()) {

                foreach ($fromform as $k => $value) {
                    if (is_numeric($k)) {
                        $check = $DB->get_record_sql("SELECT *
                            FROM {domoscio_knode_questions}
                            WHERE questionid = :qid
                            AND knodeid = :knid
                            AND type = :qtype",
                            array('qid' => $k,
                            'knid' => $notion->id,
                            'qtype' => $qtype)
                        );

                        if ($value == 1) {
                            if ($check == null) {
                                $entry = new stdClass;
                                $entry->instance = $domoscio->id;
                                $entry->knodeid = $kn;
                                $entry->questionid = $k;
                                $entry->type = $qtype;
                                $write = $DB->insert_record('domoscio_knode_questions', $entry);
                            }
                        } else if ($value == 0) {
                            if (!empty($check)) {
                                $DB->delete_records('domoscio_knode_questions', array('questionid' => $k, 'knodeid' => $notion->id, 'type' => $qtype));
                            }
                        }
                    }
                }

                print_string('upd_qlist', 'domoscio');
                echo "<hr/>".html_writer::tag('button',
                                              get_string('next_btn', 'domoscio'),
                                              array('type' => 'button',
                                                    'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id'")
                                             );

            } else {
                $mform->display();
            }
        } else {
            echo "<br/>".get_string('no_questions_in_bank', 'domoscio')."<br/>";
            $url = new moodle_url("$CFG->wwwroot/question/edit.php");
            $url->param('cmid', $cm->id);
            echo html_writer::link($url, get_string('choose_q_from_qbank', 'domoscio'));
        }
    } else {

        $list = array();

        $quizzes = $DB->get_recordset('quiz', array(), '', 'id,name');
        foreach ($quizzes as $quiz) {
            $list[] = domoscio_display_activities_list($quiz, 'quiz', $kn, $cm);
        }
        $quizzes->close();

        $scorms = $DB->get_recordset('scorm', array(), '', '*');
        foreach ($scorms as $scorm) {
            $list[] = domoscio_display_activities_list($scorm, 'scorm', $kn, $cm);
        }
        $scorms->close();

        $lessons = $DB->get_recordset('lesson', array(), '', 'id,name');
        foreach ($lessons as $lesson) {
            $list[] = domoscio_display_activities_list($lesson, 'lesson', $kn, $cm);
        }
        $lessons->close();

        // pagination
        $limit = 10;
        $offset = max(($pagenum - 1), 0) * $limit;
        $nextpage = $pagenum + 1;
        $prevpage = $pagenum - 1;
        $pagedlist = array_slice($list, $offset, $limit);
        $pagestotal = ceil((count($list)) / $limit);

        $icon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/edit'), 'alt' => get_string('edit'), 'class' => 'smallicon'));
        //$url = html_writer::link("#", $icon." ".get_string('create_q', 'domoscio')." (Coming Soon)", array('disabled' => 'disabled'));
        //$list .= "<hr/>".html_writer::tag('p', $url, array('class' => ''));

        echo html_writer::div(implode($pagedlist), '', array('class' => 'offset2'));

        if ($pagestotal > 1) {
            if ($pagenum > 1) {
              $linktourl->param('pagenum', $prevpage);
              echo html_writer::link($linktourl, get_string('prev_page', 'domoscio'));
            }
            echo html_writer::tag('span', " | ");
            if ($pagenum < $pagestotal) {
              $linktourl->param('pagenum', $nextpage);
              echo html_writer::link($linktourl, get_string('next_page', 'domoscio'));
            }
        }
    }
}

/* ----- CELLTESTS QUESTIONS -----*/

echo $OUTPUT->footer();
