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
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_domoscio_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $DB;

        $config = get_config("domoscio");
        if ($this->_cm) {
            $domoscio = $DB->get_record('domoscio', array('id' => $this->_cm->instance), '*');
            $module = $this->domoscio_get_resource_bykn($domoscio->resourceid);
        }

        $mform = $this->_form;
        if (!$config->domoscio_id || !$config->domoscio_apikey || !$config->domoscio_apiurl) {
          $mform->addElement('html', '<div class="well">'.get_string('settings_required', 'domoscio').'</div>');
        }
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
        if (method_exists($this, 'standard_intro_elements')){
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // Adding the rest of domoscio settings, spreading all them into this fieldset
        // ... or adding more fieldsets ('header' elements) if needed for better logic.
        $mform->addElement('header', 'domoscioresourceset', get_string('domoscioresourceset', 'domoscio'));

        $select = $mform->addElement('select', 'resource', get_string('resourceset_resource', 'domoscio'), $this->select_ressource(), NOGROUPS);
        $mform->addRule('resource', null, 'required', null, 'client');

        if ($this->_cm) {
            $select->setSelected($module);
        }
        $mform->addHelpButton('resource', 'domoscioresourceset', 'domoscio');

        // Add standard grading elements.
        // $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        if ($config->domoscio_id && $config->domoscio_apikey && $config->domoscio_apiurl) {
          $this->add_action_buttons();
        } else {
          $mform->addElement('html', '<hr/>');
        }
    }

    /**
     * Retrive all selectable course modules to link to the Domoscio plugin
     *
     * @return \array $datas all course modules
     */
    public function select_ressource() {

        global $COURSE, $CFG, $DB;

        $cmtypes = array('book', 'lesson', 'page', 'scorm', 'url', 'resource', 'glossary');

        $allinstances = array();

        foreach ($cmtypes as $cmtype) {
          foreach (get_all_instances_in_course($cmtype, $COURSE) as $cm) {
            $allinstances[$cm->coursemodule] = $cm->name;
          }
        }

        return $allinstances;
    }

    /**
     * Retrives course modules by its knowledge_node once the module was linked to Domoscio for edit purpose
     *
     * @param \int $knowledgenode the knowledge node id
     * @return \int $resource->id the course module id
     */
    public function domoscio_get_resource_bykn($knowledgenode) {

        global $DB, $CFG, $OUTPUT;

        $query = "SELECT {course_modules}.module, {course_modules}.instance, {course_modules}.id
                    FROM {course_modules}
              INNER JOIN {domoscio_knowledge_nodes}
                      ON {course_modules}.id = {domoscio_knowledge_nodes}.resourceid
                   WHERE {domoscio_knowledge_nodes}.knodeid = :knid";

        $resource = $DB->get_record_sql($query, array('knid' => $knowledgenode));

        return $resource->id;
    }
}
