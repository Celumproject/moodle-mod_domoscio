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
 * This is the view where an editing teacher can link a freshly created quiz
 * to an existing resource
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
require_once(dirname(__FILE__).'/classes/linkto_form.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or

$q = optional_param('q', 0, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id('domoscio', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
    $quizname   = $DB->get_record('quiz', array('id' => $q), '*', MUST_EXIST);
}

// requetes sur les ressources
$sqlbooks = "SELECT name FROM mdl_book WHERE course =".$course->id;
$sqlpages = "SELECT name FROM mdl_page WHERE course =".$course->id;

$listsbooks = $DB->get_records_sql($sqlbooks);
$listspages = $DB->get_records_sql($sqlpages);

//requetes sur les questions
$sqlquestions = "SELECT * FROM `mdl_question` INNER JOIN `mdl_quiz_slots` ON `mdl_question`.`id` = `mdl_quiz_slots`.`questionid` WHERE `mdl_quiz_slots`.`quizid` = ".$q;

$questions = $DB->get_records_sql($sqlquestions);

require_course_login($course);


$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title($domoscio->name);
$PAGE->set_heading($course->fullname." > ".$domoscio->name);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

echo $OUTPUT->heading("Associer ressource");

echo "Associez les questions du quiz <b>". $quizname->name ."</b> avec une ressource du cours :<br/><hr/>";


$datas = array();

foreach($listsbooks as $list)
{
    $datas[] = "Book ".$list->name;
}

foreach($listspages as $list)
{
    $datas[] = "Page ".$list->name;
}

//---------------------------------------------------------------------

foreach($questions as $question)
{
    $mform = new linkto_form(null, array('titles' => $question->name, 'options' => $datas));

    if ($mform->is_cancelled()) {

        redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$_GET['id']);
        exit;

    } else if ($fromform = $mform->get_data()) {

        $DB->update_record("cell_tests", $fromform);
        redirect("$CFG->wwwroot/mod/domoscio/quiz.php?id=".$fromform->knowledge_cell_id);
        exit;

    } else {


      $mform->display();
    }
}
/*
$table = new html_table();
$table->head = array('Question', 'Ressource');
$table->data = $datas;

echo html_writer::table($table);
*/

/*$mform = new linkto_form();

if ($mform->is_cancelled()) {

    exit;

} else if ($fromform = $mform->get_data()) {

    exit;

} else {

  $mform->set_data($formdata);
  $mform->display();
}*/

echo $OUTPUT->footer();
