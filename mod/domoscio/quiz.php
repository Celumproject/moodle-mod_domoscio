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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_domoscio
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/classes/quiz_form.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or

//$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

if ($id) {
    $cm         = get_coursemodule_from_id('domoscio', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
}

require_course_login($course);


$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title($domoscio->name);
$PAGE->set_heading($course->fullname." > ".$domoscio->name);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

if ($domoscio->intro) {
    echo $OUTPUT->box(format_module_intro('domoscio', $domoscio, $cm->id), 'generalbox mod_introbox', 'domosciointro');
}

// Formulaire d'inscription de questions

// Inscription nouvelle question
if($_GET["delete"] == null && $_GET['update'] == null)
{
    echo $OUTPUT->heading("Créer un quiz");

    $mform = new quiz_form();

    $formdata = array('knowledge_cell_id' => $id);

    if ($mform->is_cancelled()) {

        redirect("$CFG->wwwroot/mod/domoscio/view.php?id=".$_GET['id']);
        exit;

    } else if ($fromform = $mform->get_data()) {

        $DB->insert_record("cell_tests", $fromform);
        redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$fromform->knowledge_cell_id);
        exit;

    } else {

      $mform->set_data($formdata);
      $mform->display();
    }
}
// Mise à jour question existante


elseif($_GET["update"] == true)
{
    echo $OUTPUT->heading("Modifier une question");

    $qUpdate = $DB->get_record('cell_tests', array('id' => $_GET["q"]));

    $mform = new quiz_form();

    $formdata = array('id' => $_GET['q'],
                    'knowledge_cell_id' => $id,
                    'title' => $qUpdate->title,
                    'question' => $qUpdate->question,
                    'nature' => $qUpdate->nature,
                    'answer' => $qUpdate->answer);

    if ($mform->is_cancelled()) {

        redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$_GET['id']);
        exit;

    } else if ($fromform = $mform->get_data()) {

        $DB->update_record("cell_tests", $fromform);
        redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$fromform->knowledge_cell_id);
        exit;

    } else {

      $mform->set_data($formdata);
      $mform->display();
    }

}
// Liste de questions


elseif($_GET["delete"] == true)
{
    $DB->delete_records('cell_tests', array('knowledge_cell_id' => $_GET["id"], 'id' => $_GET["q"]));
    redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$_GET['id']);
    exit;
}

echo $OUTPUT->heading("Liste des questions");

$questions = $DB->get_records('cell_tests', array('knowledge_cell_id' => $id), '','*');

$datas = array();

foreach($questions as $question)
{
    $delete_icon = "<form action='$CFG->wwwroot/mod/domoscio/quiz.php?id=".$id."&q=".$question->id."&delete=true' method='POST'><input type='submit' value='x'></input></form>";
    $update_icon = "<form action='$CFG->wwwroot/mod/domoscio/quiz.php?id=".$id."&q=".$question->id."&update=true' method='POST'><input type='submit' value='Update'></input></form>";

    $datas[] = array($question->id, $question->title, $delete_icon, $update_icon);
}

$table = new html_table();
$table->head = array('N°', 'Titre', 'Supprimer', 'Modifier');
$table->data = $datas;


echo html_writer::table($table);

echo $OUTPUT->footer();