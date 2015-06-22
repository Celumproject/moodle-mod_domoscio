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
 * Prints a particular instance of domoscio
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
require_once(dirname(__FILE__).'/classes/create_notion_form.php');

$config = get_config('domoscio');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('d', 0, PARAM_INT);  // ... domoscio instance ID - it should be named as the first character of the module.
$kn = optional_param('kn', 0, PARAM_INT); // Knowledge_node ID (Rappels)

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $domoscio  = $DB->get_record('domoscio', array('id' => $n), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = get_coursemodule_from_instance('domoscio', $domoscio->id, $course->id, false, MUST_EXIST);
} else if ($kn) {
    $module     = $DB->get_record('modules', array('name' => 'domoscio'), '*', MUST_EXIST);
    $domoscio   = $DB->get_record('domoscio', array('resource_id' => $kn), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = $DB->get_record('course_modules', array('instance' => $domoscio->id, 'module' => $module->id), '*', MUST_EXIST);
    $id         = $cm->id;
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);


// Print the page header.
$PAGE->set_url('/mod/domoscio/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($domoscio->name));
$PAGE->set_heading("Domoscio for Moodle");

// Output starts here.

echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading(get_string('add_notion_btn', 'domoscio'));

$rest = new domoscio_client();

$resource = json_decode($rest->setUrl($config, 'knowledge_nodes', $domoscio->resource_id)->get());

$linked_resource = get_resource_info($resource->id);

echo html_writer::tag('div', html_writer::tag('b', get_string('new_notion_intro', 'domoscio'), array('class' => 'mod_introbox')), array('class' => 'block'));

if (user_has_role_assignment($USER->id,3)) {

  $mform = new create_notion_form("$CFG->wwwroot/mod/domoscio/create_notion.php?id=$cm->id");

  if ($mform->is_cancelled()) {

      redirect("$CFG->wwwroot/mod/domoscio/select_notions.php?id=".$cm->id);
      exit;

  } else if ($fromform = $mform->get_data()) {

      $kngraph  = $DB->get_record('knowledge_graphs', array('course_id' => $course->id), '*', MUST_EXIST);
      $rest = new domoscio_client();

      $json = json_encode(array('knowledge_graph_id' => strval($kngraph->knowledge_graph_id),
                                  'name' => strval($fromform->notion)));

      $new_notion = json_decode($rest->setUrl($config, 'knowledge_nodes', null)->post($json));

      //print_r($new_notion);

      // Add new entry into knowledge_nodes table
      $record = new stdClass();
      $record->knowledge_node_id = $new_notion->id;
      $record->instance = $domoscio->id;
      $record->resource_id = $linked_resource->cm;
      $record->child_id = null;

      $insert = $DB->insert_record('knowledge_nodes', $record);

      // Ajoute les knowledge edges
      $json = json_encode(array('knowledge_graph_id' => strval($kngraph->knowledge_graph_id),
                                  'source_node_id' => strval($resource->id),
                                  'destination_node_id' => strval($new_notion->id)));

      $knedge = json_decode($rest->setUrl($config, 'knowledge_edges', null)->post($json));

      echo "La notion a bien été créée.<hr/>".html_writer::link("$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id", '<< '.get_string('back_btn', 'domoscio')."&nbsp");

  } else {
    $mform->display();
  }
}

// Finish the page.
echo $OUTPUT->footer();
