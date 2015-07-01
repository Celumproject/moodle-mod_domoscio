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
* @copyright  2015 Domoscio
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once(dirname(dirname(__FILE__)).'/lib.php');

//$PAGE->requires->js('/mod/domoscio/bootstrap-collapse.js', true);

class linkto_form extends moodleform {

    public function definition() {
        global $DB, $CFG, $OUTPUT;

        $mform = $this->_form;

        //requetes sur les questions déjà sélectionnées le cas échéant
        $selectedquestions = $DB->get_records_sql("SELECT *
                                                     FROM ".$CFG->prefix."knowledge_node_questions
                                                    WHERE `knowledge_node`=".$this->_customdata['kn_id']."
                                                      AND `type` = '".$this->_customdata['module']."'");

        $selected = array();

        foreach($selectedquestions as $selectedquestion)
        {
            $selected[] = $selectedquestion->question_id;
        }

        if($this->_customdata['module'] != 'scorm')
        {
            $quiz = $DB->get_record('quiz', array('id' => $this->_customdata['exo_id']), 'id,name');

            $icon = html_writer::tag('img', '', array('src'=>$OUTPUT->pix_url('icon','quiz','quiz',array('class'=>'icon')), 'class'=>'activityicon', 'alt'=>'disable'));

            $sqlquestions = "SELECT ".$CFG->prefix."question.`id`, ".$CFG->prefix."question.`name`, ".$CFG->prefix."question.`questiontext`
                               FROM ".$CFG->prefix."question
                         INNER JOIN ".$CFG->prefix."quiz_slots
                                 ON ".$CFG->prefix."question.`id` = ".$CFG->prefix."quiz_slots.`questionid`
                              WHERE ".$CFG->prefix."quiz_slots.`quizid` = ".$quiz->id;

            $questions = $DB->get_records_sql($sqlquestions);

            //$mform->addElement('html', "<div class='accordion' id='accordion'>");
            $mform->addElement('html', "<h5 class='well well-small accordion-toggle' data-toggle='collapse' data-parent='#accordion'><a href='#collapse-".$quiz->id."'>".$icon.$quiz->name."</a></h5>");


            //$mform->addElement('html', "<div class='accordion-body collapse' id='collapse-".$quiz->id."'>");
            //$mform->addElement('html', "<div class='accordion-inner'>");

            foreach($questions as $question)
            {
                if(in_array($question->id, $selected)){$check = true;}else{$check = false;}

                $mform->addElement('advcheckbox', $question->id, $question->id." ".$question->name, "<hr/>".$question->questiontext, array('group' => 1), array(0, 1))->setChecked($check);
            }

            //$mform->addElement('html', "</div></div></div>");

        }

        else
        {
            $scoes = $DB->get_records('scorm_scoes', array('scormtype' => 'sco', 'scorm' => $this->_customdata['exo_id']), '', '*');

            $icon = html_writer::tag('img', '', array('src'=>$OUTPUT->pix_url('icon','scorm','scorm',array('class'=>'icon')), 'class'=>'activityicon', 'alt'=>'disable'));

            $scormflag = '';

            foreach($scoes as $sco)
            {
                if(in_array($sco->id, $selected)){$check = true;}else{$check = false;}

                if($scormflag != $sco->scorm)
                {
                    $scorm_title = $DB->get_record('scorm', array('id' => $sco->scorm), 'name');
                    $mform->addElement('html', "<h5 class='well well-small accordion-toggle' data-toggle='collapse' data-parent='#accordion'><a href='#collapse-".$sco->id."'>".$icon.$scorm_title->name."</a></h5>");

                    $scormflag = $sco->scorm;
                }

                $mform->addElement('advcheckbox', $sco->id, '', $sco->title, array('group' => 1), array(0, 1))->setChecked($check);
            }

        }

        $this->add_action_buttons();
    }
}
