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
 * This view call tests for the student
 *
 * If linked exercise is SCORM package, the SCORM player is called.
 * Else if Quiz questions, Quiz interface is called.
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');


$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$kn = optional_param('kn', 0, PARAM_INT); // Knowledge_node ID (Rappels)
$solo = optional_param('solo', false, PARAM_INT); // Si test unitaire
$t = optional_param('t', null, PARAM_INT); // Start test timestamp
//$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

if($solo == 'true'){$_SESSION['todo'] = null;};
if($t){$_SESSION['start'] = $t;};

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($kn) {
    $module     = $DB->get_record('modules', array('name' => 'domoscio'), '*', MUST_EXIST);
    $instance   = $DB->get_record('knowledge_nodes', array('knowledge_node_id' => $kn), '*', MUST_EXIST);
    $domoscio   = $DB->get_record('domoscio', array('id' => $instance->instance), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = $DB->get_record('course_modules', array('instance' => $domoscio->id, 'module' => $module->id), '*', MUST_EXIST);
    $id         = $cm->id;
}
$config = get_config('domoscio');
$PAGE->set_context(context_system::instance());
require_login();
$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/doquiz.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_pagelayout('incourse');

$url_r = "$CFG->wwwroot/mod/domoscio/results.php";
// Récupère les identifiants des questions sélectionnées par le concepteur

$lists = $DB->get_records('knowledge_node_questions', array('instance' => $domoscio->id, 'knowledge_node' => $kn), '', 'question_id');

if($domoscio->resource_type == "scorm")
{
    if(!empty($lists))
    {
        $selected = array_rand($lists, 1);

        $scorm = $DB->get_record('scorm_scoes', array('id' => $selected), '*');

        $_GET['domoscioid'] = $temp = $cm->id;

        $_GET['a'] = $scorm->scorm;

        $_GET['scoid'] = $selected;

        include('player.php');

        $content = "<input type='hidden' value=$scoid name=scoid></input><input type='hidden' value=$attempt name=attempt></input>";
        $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next'));
        $params = "id=$temp&scorm=".$_GET['a']."&kn=$kn";

        $output = html_writer::tag('form', $content, array('method' => 'POST', 'action' => $url_r.'?'.$params, 'id' => 'responseform'));
        echo $output;
    }
    else
    {
        echo html_writer::tag('blockquote', get_string('tests_empty', 'domoscio'), array('class' => 'muted'));
    }
}
else
{
    echo $OUTPUT->header();
    $PAGE->set_title(get_string('test_session', 'domoscio'));

    echo $OUTPUT->heading(get_string('test_session', 'domoscio'));

    if(!empty($lists))
    {
        $selected = array_rand($lists, 1);

        // Récupère les informations relatives aux questions sélectionnées
        if($domoscio->resource_type == "scorm"){$table = "celltests";}else{$table = "question";}
        $question = $DB->get_record($table, array('id' => $selected), '*');
        $qinstance = "kn_q".$question->id;

        $content = html_writer::tag('input', '', array('type' => 'hidden', 'value' => $domoscio->id, 'name' => $qinstance))
                  .display_questions($question, $domoscio->resource_type);
        $params = "kn=$kn&q=$selected";

        $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next'));
        $output = html_writer::tag('form', $content, array('method' => 'POST', 'action' => $url_r.'?'.$params, 'id' => 'responseform'));
        echo $output;
    }
    else
    {
        echo html_writer::tag('blockquote', get_string('tests_empty', 'domoscio'), array('class' => 'muted'));
    }
}
echo html_writer::tag('button', get_string('end_btn', 'domoscio'), array('type' => 'button',
                                                                       'onclick'=>"javascript:location.href='$CFG->wwwroot/mod/domoscio/results.php?end=true'"));



echo $OUTPUT->footer();
