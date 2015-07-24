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
 * This view displays question creation interface (not yet implemented)
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/classes/create_celltest_form.php');

$id = optional_param('id', 0, PARAM_INT);
$q = optional_param('q', 0, PARAM_INT); // Course_module ID, or
$update = optional_param('update', null, PARAM_BOOL);
$delete = optional_param('delete', null, PARAM_BOOL);

if ($id) {
    $cm         = get_coursemodule_from_id('domoscio', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
}

require_course_login($course);
$context = context_module::instance($cm->id);
$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title($domoscio->name);
$PAGE->set_heading($course->fullname." > ".$domoscio->name);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

if (has_capability('moodle/course:create', $context)) {
    if ($domoscio->intro) {
        echo $OUTPUT->box(format_module_intro('domoscio', $domoscio, $cm->id), 'generalbox mod_introbox', 'domosciointro');
    }

    // Creating questions form

    // Writing new question
    if ($delete == null && $update == null) {
        echo $OUTPUT->heading(get_string('create_q', 'domoscio'));

        $mform = new mod_domoscio_create_celltest_form();

        $formdata = array('knowledge_cell_id' => $id);

        if ($mform->is_cancelled()) {

            redirect("$CFG->wwwroot/mod/domoscio/view.php?id=$id");
            exit;

        } else if ($fromform = $mform->get_data()) {

            $DB->insert_record("domoscio_celltests", $fromform);
            redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$fromform->knowledge_cell_id);
            exit;

        } else {

            $mform->set_data($formdata);
            $mform->display();
        }
    } else if ($update == true) {
        // Updating existing question
        echo $OUTPUT->heading(get_string('create_q', 'domoscio'));

        $qupdate = $DB->get_record('domoscio_celltests', array('id' => $q));

        $mform = new mod_domoscio_create_celltest_form();

        $formdata = array('id' => $q,
           'knowledge_cell_id' => $id,
                       'title' => $qupdate->title,
                    'question' => $qupdate->question,
                      'nature' => $qupdate->nature,
                      'answer' => $qupdate->answer);

        if ($mform->is_cancelled()) {

            redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=$id");
            exit;

        } else if ($fromform = $mform->get_data()) {

            $DB->update_record("domoscio_celltests", $fromform);
            redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$fromform->knowledge_cell_id);
            exit;

        } else {

            $mform->set_data($formdata);
            $mform->display();
        }

    } else if ($delete == true) {
        // Question list
        $DB->delete_records('domoscio_celltests', array('knowledge_cell_id' => $id, 'id' => $q));
        redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$id);
        exit;
    }

    echo $OUTPUT->heading(get_string('create_q', 'domoscio'));

    $questions = $DB->get_records('domosico_celltests', array('knowledge_cell_id' => $id), '', '*');

    $datas = array();

    foreach ($questions as $question) {
        $deleteicon = "<form action='$CFG->wwwroot/mod/domoscio/quiz.php?id=".$id."&q=".$question->id."&delete=true' method='POST'>
                       <input type='submit' value='x'></input></form>";
        $updateicon = "<form action='$CFG->wwwroot/mod/domoscio/quiz.php?id=".$id."&q=".$question->id."&update=true' method='POST'>
                       <input type='submit' value='Update'></input></form>";

        $datas[] = array($question->id, $question->title, $deleteicon, $updateicon);
    }

    $table = new html_table();
    $table->head = array(get_string('num', 'domoscio'), get_string('notion_title', 'domoscio'), get_string('delete', 'domoscio'), get_string('edit', 'domoscio'));
    $table->data = $datas;

    echo html_writer::table($table);
}
echo $OUTPUT->footer();
