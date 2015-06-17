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
 * The main domoscio configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_domoscio
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_domoscio
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_domoscio_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $DB;

        if($this->_cm)
        {
            $domoscio = $DB->get_record('domoscio', array('id' => $this->_cm->instance), '*');
            $module = $this->get_resource_bykn($domoscio->resource_id);
        }

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('domoscioname', 'domoscio'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'domoscioname', 'domoscio');

        // Adding the standard "intro" and "introformat" fields.
        $this->add_intro_editor();

        // Adding the rest of domoscio settings, spreading all them into this fieldset
        // ... or adding more fieldsets ('header' elements) if needed for better logic.
        #$mform->addElement('static', 'label1', 'domosciosetting1', 'Your domoscio fields go here. Replace me!');

        $mform->addElement('header', 'domoscioresourceset', get_string('domoscioresourceset', 'domoscio'));

        $select = $mform->addElement('select', 'resource', get_string('resourceset_resource', 'domoscio'), $this->select_ressource(), NOGROUPS);

        if($this->_cm)
        {
            $select->setSelected($module);
        }
        $mform->addHelpButton('resource', 'domoscioresourceset', 'domoscio');

        // Add standard grading elements.
        #$this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    function select_ressource() {

        global $COURSE, $CFG, $DB;

        // Recherche les course modules à afficher
        $query = "SELECT * FROM ".$CFG->prefix."course_modules WHERE `module` IN (3,14,16,18)";

        $modules = $DB->get_records_sql($query);

        $datas = array();

        foreach($modules as $module)
        {
            $datas[$module->id] = $this->get_resource_info($module->module, $module->instance);
        }

        return $datas;
    }

    function get_resource_info($module, $instance) {

        global $DB, $CFG;

        $modulename = null;

        switch($module) {
            case 3:
                $modulename = "book";
                break;

            case 14:
                $modulename = "lesson";
                break;

            case 16:
                $modulename = "page";
                break;

            case 18:
                $modulename = "scorm";
                break;
        }

        $moduleinfo = $DB->get_record($modulename, array('id' => $instance), 'name');

        return $moduleinfo->name;
    }

    function get_resource_bykn($knowledge_node) {

        global $DB, $CFG, $OUTPUT;

        $query = "SELECT ".$CFG->prefix."course_modules.`module`,".$CFG->prefix."course_modules.`instance`, ".$CFG->prefix."course_modules.`id`
                    FROM ".$CFG->prefix."course_modules
              INNER JOIN ".$CFG->prefix."knowledge_nodes
                      ON ".$CFG->prefix."course_modules.`id` = ".$CFG->prefix."knowledge_nodes.`resource_id`
                   WHERE ".$CFG->prefix."knowledge_nodes.`knowledge_node_id` =".$knowledge_node;

        $resource = $DB->get_record_sql($query);

        return $resource->id;
    }
}
