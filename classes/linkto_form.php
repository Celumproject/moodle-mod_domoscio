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

        //requetes sur les questions
        $sqlquestions = "SELECT * FROM `mdl_question` INNER JOIN `mdl_quiz_slots` ON `mdl_question`.`id` = `mdl_quiz_slots`.`questionid` WHERE `mdl_quiz_slots`.`quizid` = $this->_customdata";

        $questions = $DB->get_records_sql($sqlquestions);

        $mform = $this->_form;

        foreach($questions as $question)
        {
            $mform->addElement('advcheckbox', $question->questionid, $question->name, "<hr/>".$question->questiontext, array('group' => 1), array(0, 1));
        }

        $this->add_action_buttons();
    }
}
