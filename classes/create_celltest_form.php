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
 * The form to be displayed when creating new questions
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
/**
 * Create celltest form class
 *
 * @package    mod_domoscio
 * @since      Moodle 2.8
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_domoscio_create_celltest_form extends moodleform {
    /**
     * The form
     *
     * @return void
     */
    public function definition() {
        global $CFG;

        $quiztypes = array("open" => "Question ouverte", "qcm" => "QCM", "hole" => "Texte à trous", "dragdrop" => "Appairage", "eval" => "Barème");

        $mform = $this->_form;

        $mform->addElement('hidden', 'knowledge_cell_id');
        $mform->addElement('text', 'title', "Titre de la question");
        $mform->addElement('textarea', 'question', 'Question');

        $mform->addElement('select', 'nature', 'Type de question', $quiztypes, $attributes);
        $mform->addElement('textarea', 'answer', 'Réponse');

        $mform->setType('knowledge_cell_id', PARAM_INT);
        $mform->setType('title', PARAM_NOTAGS);
        $mform->setType('enonce', PARAM_RAW);
        $mform->setType('question', PARAM_RAW);

        $this->add_action_buttons();
    }
}
