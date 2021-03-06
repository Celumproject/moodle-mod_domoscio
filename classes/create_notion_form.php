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
 * The class defining the form displayed on the create notion view
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
 * Create notion form class
 *
 * @package    mod_domoscio
 * @since      Moodle 2.8
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_domoscio_create_notion_form extends moodleform {
    /**
     * The form
     *
     * @return void
     */
    public function definition() {
        global $DB, $CFG;

        $attributes = array('size' => '30');

        $mform = $this->_form;
        // Adding text field for new notion
        $mform->addElement('text', 'notion', get_string('notion_title', 'domoscio'), $attributes);

        $mform->setType('notion', PARAM_ALPHANUMEXT);
        $mform->addRule('notion', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_action_buttons();
    }
}
