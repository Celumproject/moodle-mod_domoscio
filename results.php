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
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_domoscio
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/classes/quiz_form.php');
require_once(dirname(__FILE__).'/lib.php');


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


echo $OUTPUT->heading("Résultats");

$list = $_POST['list'];

// Récupère les informations relatives aux questions sélectionnées
$sqlquestitem = "SELECT *
                FROM `mdl_question`
                WHERE `id` IN ($list)";

$questions = $DB->get_records_sql($sqlquestitem);

foreach($questions as $question)
{
    $qtype = $question->qtype;

    echo "<div class='que ".$qtype." deferredfeedback notyetanswered'>";
        echo "<div class='info'>";
            echo "<h3 class='no'>Question <span class='qno'>".$question->id."</span></h3>";
        echo "</div>";
        echo "<div class='content'>";
            echo "<div class='formulation'>";
                if($qtype == "calculated" || $qtype == "numerical" || $qtype == "shortanswer")
                {
                    echo get_inputresult($question, $_POST);
                }
                elseif($qtype == "multichoice" || $qtype == "calculatedmulti" || $qtype == "truefalse")
                {
                    echo get_multichoiceresult($question, $_POST);
                }
                elseif($qtype == "multianswer")
                {
                    echo get_multiresult($question, $_POST);
                }
                elseif($qtype == "match")
                {
                    echo  get_matchresult($question, $_POST);
                }
            echo "</div>";
        echo "</div>";
    echo "</div>";
}
print_r($_POST);
echo $OUTPUT->footer();
