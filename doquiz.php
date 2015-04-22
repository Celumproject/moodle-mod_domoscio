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

require_course_login($course);

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title('Evaluation');
$PAGE->set_heading("Domoscio for Moodle");
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

echo $OUTPUT->heading("Evaluation");

// Récupère les identifiants des questions sélectionnées par le concepteur

$lists = $DB->get_records_sql("SELECT `question_id` FROM `mdl_knowledge_node_questions` WHERE `instance` = $domoscio->id");

$selected = array_rand($lists, 1);

// Récupère les informations relatives aux questions sélectionnées

$question = $DB->get_record_sql("SELECT * FROM `mdl_question` WHERE `id` = $selected");

$qtype = $question->qtype;
// Créé un nouveau formulaire qui collectera toutes les données du test
echo "<form id='responseform' method='POST' action='$CFG->wwwroot/mod/domoscio/results.php?id=$cm->id&q=$selected'>";

    echo "<div class='que ".$qtype." deferredfeedback notyetanswered'>";
        echo "<div class='info'>";
            echo "<h3 class='no'>Question <span class='qno'>".$question->id."</span></h3>";
        echo "</div>";
        echo "<div class='content'>";
            echo "<div class='formulation'>";
                if($qtype == "calculated" || $qtype == "numerical" || $qtype == "shortanswer")
                {
                    echo get_inputanswer($question);
                }
                elseif($qtype == "multichoice" || $qtype == "calculatedmulti" || $qtype == "truefalse")
                {
                    echo get_multichoiceanswer($question);
                }
                elseif($qtype == "multianswer")
                {
                    echo get_multianswer($question);
                }
                elseif($qtype == "match")
                {
                    echo  get_match($question);
                }
            echo "</div>";
        echo "</div>";
    echo "</div>";

echo "<input type='submit' value='Next' name='next'></input>";
echo "</form>";

echo $OUTPUT->footer();
