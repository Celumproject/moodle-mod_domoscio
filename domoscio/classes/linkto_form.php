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
/**
 * Linkto form class
 *
 * @package    mod_domoscio
 * @since      Moodle 2.8
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_domoscio_linkto_form extends moodleform {
    /**
     * The form
     *
     * @return void
     */
    public function definition() {
        global $DB, $CFG, $OUTPUT;

        $mform = $this->_form;

        // Retrive already selected questions
        $selectedquestions = $DB->get_records_sql("SELECT *
                                                     FROM {domoscio_knode_questions}
                                                    WHERE `knodeid`= :knid
                                                      AND `type` = :type",
                                                  array('knid' => $this->_customdata['kn_id'],
                                                        'type' => $this->_customdata['module'])
                                                 );

        $selected = array();

        foreach ($selectedquestions as $selectedquestion) {
            $selected[] = $selectedquestion->questionid;
        }

        if ($this->_customdata['module'] == 'quiz') {
            $quiz = $DB->get_record('quiz', array('id' => $this->_customdata['exo_id']), 'id,name');

            $icon = html_writer::tag('img', '', array('src' => $OUTPUT->pix_url('icon', 'quiz', 'quiz', array('class' => 'icon')), 'class' => 'activityicon', 'alt' => 'disable'));

            $sqlquestions = "SELECT {question}.`id`, {question}.`name`, {question}.`questiontext`
                               FROM {question}
                         INNER JOIN {quiz_slots}
                                 ON {question}.`id` = {quiz_slots}.`questionid`
                              WHERE {quiz_slots}.`quizid` = :quizid";

            $questions = $DB->get_recordset_sql($sqlquestions, array('quizid' => $quiz->id));

            $mform->addElement('html', "<h5 class='well well-small accordion-toggle' data-toggle='collapse' data-parent='#accordion'><a href='#collapse-".
                                        $quiz->id."'>".
                                        $icon.$quiz->name."</a></h5>");

            foreach ($questions as $question) {
                if (in_array($question->id, $selected)) {
                    $check = true;
                } else {
                    $check = false;
                }

                $mform->addElement('advcheckbox',
                                    $question->id,
                                    $question->id." ".$question->name,
                                    "<hr/>".$question->questiontext,
                                    array('group' => 1), array(0, 1))->setChecked($check);
            }
            $questions->close();
            
        } else if ($this->_customdata['module'] == 'lesson') {
            $lesson = $DB->get_record('lesson', array('id' => $this->_customdata['exo_id']), 'id,name');

            list($insql, $inparams) = $DB->get_in_or_equal(array(1, 2, 3, 5, 8, 10));

            $sql = "SELECT `id`, `contents`, `qtype`, `title`
                      FROM {lesson_pages}
                     WHERE `qtype` $insql";

            $questions = $DB->get_records_sql($sql, $inparams);

            $icon = html_writer::tag('img',
                                     '',
                                     array('src' => $OUTPUT->pix_url('icon', 'lesson', 'lesson', array('class' => 'icon')),
                                         'class' => 'activityicon', 'alt' => 'disable'));

            $mform->addElement('html', "<h5 class='well well-small accordion-toggle' data-toggle='collapse' data-parent='#accordion'><a href='#collapse-".
                                        $lesson->id."'>".
                                        $icon.$lesson->name."</a></h5>");

            foreach ($questions as $question) {
                if (in_array($question->id, $selected)) {
                    $check = true;
                } else {
                    $check = false;
                }

                $mform->addElement('advcheckbox',
                                  $question->id,
                                  $question->id." ".$question->title." (".$question->qtype.")",
                                  $question->contents."<hr/>",
                                  array('group' => 1), array(0, 1))->setChecked($check);
            }
        } else {
            $scoes = $DB->get_records('scorm_scoes', array('scormtype' => 'sco', 'scorm' => $this->_customdata['exo_id']), '', '*');

            $icon = html_writer::tag('img', '', array('src' => $OUTPUT->pix_url('icon',
                                                                                'scorm',
                                                                                'scorm',
                                                                                array('class' => 'icon')),
                                                                                'class' => 'activityicon',
                                                                                'alt' => 'disable'));

            $scormflag = '';

            foreach ($scoes as $sco) {
                if (in_array($sco->id, $selected)) {
                    $check = true;
                } else {
                    $check = false;
                }

                if ($scormflag != $sco->scorm) {
                    $scormtitle = $DB->get_record('scorm', array('id' => $sco->scorm), 'name');
                    $mform->addElement('html', "<h5 class='well well-small accordion-toggle' data-toggle='collapse' data-parent='#accordion'><a href='#collapse-".
                                                $sco->id."'>".$icon.$scormtitle->name."</a></h5>");
                    $scormflag = $sco->scorm;
                }
                $mform->addElement('advcheckbox', $sco->id, '', $sco->title, array('group' => 1), array(0, 1))->setChecked($check);
            }
        }
        $this->add_action_buttons();
    }
}
