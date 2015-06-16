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
* The class defining the form displayed on the linkto view
*
* It uses the standard core Moodle formslib. For more info about them, please
* visit: http://docs.moodle.org/en/Development:lib/formslib.php
*
* @package    mod_domoscio
* @copyright  2015 Your Name
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");


/**
* Module instance settings form
*
* @package    mod_domoscio
* @copyright  2015 Your Name
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
class linkto_form extends moodleform {

    public function definition() {
        global $DB, $CFG;

        $quizzes = $DB->get_records('quiz', array('course' => $this->_customdata['course']), '', 'id,name');

        $mform = $this->_form;

        foreach($quizzes as $quiz)
        {
            $mform->addElement('html', "<h5 class='coursebox'>".$quiz->name."</h5>");

            $sqlquestions = "SELECT ".$CFG->prefix."question.`id`, ".$CFG->prefix."question.`name`, ".$CFG->prefix."question.`questiontext` FROM ".$CFG->prefix."question INNER JOIN ".$CFG->prefix."quiz_slots ON ".$CFG->prefix."question.`id` = ".$CFG->prefix."quiz_slots.`questionid` WHERE ".$CFG->prefix."quiz_slots.`quizid` = ".$quiz->id;

            $questions = $DB->get_records_sql($sqlquestions);

            //requetes sur les questions déjà sélectionnées le cas échéant
            $selectedquestions = $DB->get_records_sql("SELECT * FROM ".$CFG->prefix."knowledge_node_questions WHERE `knowledge_node`=".$this->_customdata['kn_id']);

            $selected = array();

            foreach($selectedquestions as $selectedquestion)
            {
                $selected[] = $selectedquestion->question_id;
            }

            foreach($questions as $question)
            {
                if(in_array($question->id, $selected)){$check = true;}else{$check = false;}

                $mform->addElement('advcheckbox', $question->id, $question->id." ".$question->name, "<hr/>".$question->questiontext, array('group' => 1), array(0, 1))->setChecked($check);
            }
        }

        $this->add_action_buttons();
    }
}
