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

$config = get_config('domoscio');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$q = optional_param('q', 0, PARAM_INT);
$kn = optional_param('notion', 0, PARAM_INT);
$exo = optional_param('exo', null, PARAM_ALPHANUMEXT);

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
}

$context = context_module::instance($cm->id);
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

$linkedmodule = domoscio_get_resource_info($resource->id);


/* ----- MOODLE QUIZ QUESTIONS -----*/

if (has_capability('moodle/course:create', $context)) {

    if ($exo == null) {
        echo html_writer::tag('div', get_string('linkto_intro', 'domoscio').
             html_writer::tag('b', $linkedmodule->display." - ".$notion->name, array('class' => '')), array('class' => 'well well-small'));
        echo html_writer::link("$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id", '<< '.get_string('back_btn', 'domoscio')."&nbsp");

        $quizzes = $DB->get_records('quiz', array(), '', 'id,name');
        $scorms = $DB->get_records('scorm', array(), '', '*');
        $lessons = $DB->get_records('lesson', array(), '', 'id,name');

        $list = '';

        foreach ($quizzes as $quiz) {
            $list .= domoscio_display_activities_list($quiz, 'quiz', $kn, $cm);
        }

        foreach ($scorms as $scorm) {
            $list .= domoscio_display_activities_list($scorm, 'scorm', $kn, $cm);
        }

        foreach ($lessons as $lesson) {
            $list .= domoscio_display_activities_list($lesson, 'lesson', $kn, $cm);
        }
        $icon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/edit'), 'alt' => get_string('edit'), 'class' => 'smallicon'));
        $url = html_writer::link("#", $icon." ".get_string('create_q', 'domoscio')." (Coming Soon)", array('disabled' => 'disabled'));
        $list .= "<hr/>".html_writer::tag('p', $url, array('class' => ''));

        echo html_writer::div($list, '', array('class' => 'offset2'));
    } else {
        $selected = explode('_', $exo);

        if ($selected[0] == 'quiz') {
            $cap = 'mod/quiz:manage';
            $module = 16;
        } else if ($selected[0] == 'scorm') {
            $cap = 'mod/scorm:viewreport';
            $module = 18;
        } else if ($selected[0] == 'lesson') {
            $cap = 'mod/lesson:manage';
            $module = 13;
        }
        $cmid = $DB->get_record('course_modules', array('instance' => $selected[1], 'module' => $module));
        $cmcontext = context_module::instance($cmid->id);

        if (has_capability($cap, $cmcontext)) {
            $mform = new mod_domoscio_linkto_form("$CFG->wwwroot/mod/domoscio/linkto.php?id=$cm->id&notion=$kn&exo=$exo",
                                                  array('kn_id' => $kn, 'module' => $selected[0], 'exo_id' => $selected[1]));

            if ($mform->is_cancelled()) {
                redirect("$CFG->wwwroot/mod/domoscio/linkto.php?id=$cm->id&notion=$kn");
                exit;
            } else if ($fromform = $mform->get_data()) {

                foreach ($fromform as $k => $value) {
                    if (is_numeric($k)) {
                        $check = $DB->get_record_sql("SELECT *
                                                        FROM {domoscio_knode_questions}
                                                       WHERE `questionid` = :qid
                                                         AND knodeid = :knid
                                                         AND type = :qtype",
                                                     array('qid' => $k,
                                                          'knid' => $notion->id,
                                                         'qtype' => $selected[0])
                                                    );

                        if ($value == 1) {
                            if ($check == null) {
                                $entry = new stdClass;
                                $entry->instance = $domoscio->id;
                                $entry->knodeid = $kn;
                                $entry->questionid = $k;
                                $entry->type = $selected[0];
                                $write = $DB->insert_record('domoscio_knode_questions', $entry);
                            }
                        } else if ($value == 0) {
                            if (!empty($check)) {
                                $DB->delete_records('domoscio_knode_questions', array('questionid' => $k, 'knodeid' => $notion->id, 'type' => $selected[0]));
                            }
                        }
                    }
                }

                echo get_string('upd_qlist', 'domoscio')."<hr/>";
                echo html_writer::tag('button',
                                      get_string('next_btn', 'domoscio'),
                                      array('type' => 'button',
                                            'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id'")
                                     );

            } else {
                $mform->display();
            }
        } else {
            echo get_string('nocapabilitytousethisservice', 'error');
        }
    }
}

/* ----- CELLTESTS QUESTIONS -----*/

echo $OUTPUT->footer();
