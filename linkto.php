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
 * This is the view where an editing teacher can link a freshly created quiz
 * to an existing resource
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
require_once(dirname(__FILE__).'/classes/linkto_form.php');

$config = get_config('domoscio');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or

$q = optional_param('q', 0, PARAM_INT);

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
    $quizname   = $DB->get_record('quiz', array('id' => $q), '*', MUST_EXIST);
}


require_course_login($course);


$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title($domoscio->name);
$PAGE->set_heading($course->fullname." > ".$domoscio->name);
$PAGE->set_pagelayout('incourse');

$rest = new domoscio_client();

$resource = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_nodes/$domoscio->resource_id?token=$config->domoscio_apikey")->get());

echo $OUTPUT->header();

echo $OUTPUT->heading("Associer ressource");

echo "Sélectionnez les questions du quiz <b>". $quizname->name ."</b> à proposer pour ce plugin :<br/>
Ces questions seront liées à la ressource : ".get_resource_info($resource->id)."<hr/>";

$mform = new linkto_form("$CFG->wwwroot/mod/domoscio/linkto.php?id=".$cm->id."&q=".$q, array('q'=>$q, 'instance'=>$domoscio->id));

if ($mform->is_cancelled()) {

    redirect("$CFG->wwwroot/mod/domoscio/view.php?id=".$cm->id);
    exit;

} else if ($fromform = $mform->get_data()) {

    foreach($fromform as $k => $value)
    {
        if(is_numeric($k))
        {
            if($value == 1)
            {
                $check = $DB->get_record_sql("SELECT * FROM `mdl_knowledge_node_questions` WHERE `question_id` = $k AND instance = $domoscio->id");

                if($check == null)
                {
                    $entry = new stdClass;
                    $entry->instance = $domoscio->id;
                    $entry->question_id = $k;
                    $write = $DB->insert_record('knowledge_node_questions', $entry);
                }
            }
            elseif($value == 0)
            {
                $check = $DB->get_record_sql("SELECT * FROM `mdl_knowledge_node_questions` WHERE `question_id` = $k AND instance = $domoscio->id");

                if(!empty($check))
                {
                    $DB->delete_records('knowledge_node_questions', array('question_id' => $k, 'instance' => $domoscio->id));
                }
            }
        }
    }

    echo "La liste des questions est mise à jour.<hr/>";
    echo html_writer::tag('button', 'Continue', array('type' => 'button','onclick'=>"javascript:location.href='$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id'"));

} else {


  $mform->display();
}

/*
$table = new html_table();
$table->head = array('Question', 'Ressource');
$table->data = $datas;

echo html_writer::table($table);
*/

/*$mform = new linkto_form();

if ($mform->is_cancelled()) {

    exit;

} else if ($fromform = $mform->get_data()) {

    exit;

} else {

  $mform->set_data($formdata);
  $mform->display();
}*/

echo $OUTPUT->footer();
