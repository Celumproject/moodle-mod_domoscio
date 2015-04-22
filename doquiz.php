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
require_once(dirname(__FILE__).'/sdk/client.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or

//$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
}
$config = get_config('domoscio');

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_heading("Domoscio for Moodle");
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

// Récupère les identifiants des questions sélectionnées par le concepteur
if ($id) {
    $PAGE->set_title('Evaluation');
    echo $OUTPUT->heading("Evaluation");
    $lists = $DB->get_records_sql("SELECT `question_id` FROM `mdl_knowledge_node_questions` WHERE `instance` = $domoscio->id");

    $selected = array_rand($lists, 1);

    // Récupère les informations relatives aux questions sélectionnées

    $question = $DB->get_record_sql("SELECT * FROM `mdl_question` WHERE `id` = $selected");

// Créé un nouveau formulaire qui collectera toutes les données du test
    echo "<form id='responseform' method='POST' action='$CFG->wwwroot/mod/domoscio/results.php?id=$cm->id&q=$selected'>";
    echo display_questions($question);
} else {
    $PAGE->set_title('Rappels');
    echo $OUTPUT->heading("Rappels");

    $todo_tests = count_tests($config);
    $qid = array();
    foreach($todo_tests as $test)
    {
        $instance = $DB->get_record_sql("SELECT `instance` FROM `mdl_knowledge_node_students` WHERE `kn_student_id` = $test");
        $lists = $DB->get_records_sql("SELECT `question_id` FROM `mdl_knowledge_node_questions` WHERE `instance` = $instance->instance");

        $selected = array_rand($lists, 1);

        // Récupère les informations relatives aux questions sélectionnées

        $question = $DB->get_record_sql("SELECT * FROM `mdl_question` WHERE `id` = $selected");
        $qid[] = $question->id;
        // Créé un nouveau formulaire qui collectera toutes les données du test
        echo "<form id='responseform' method='POST' action='$CFG->wwwroot/mod/domoscio/results.php'>";
        echo display_questions($question);
    }
    $qids = implode(',', $qid);
    echo "<input type='hidden' value=$qids name='qids'></input>";
}

echo "<input type='submit' value='Next' name='next'></input>";
echo "</form>";

echo $OUTPUT->footer();
