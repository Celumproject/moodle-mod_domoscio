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

echo $OUTPUT->header();
$PAGE->set_title(get_string('test_session', 'domoscio'));
echo $OUTPUT->heading(get_string('test_session', 'domoscio'));
$url_r = "$CFG->wwwroot/mod/domoscio/results.php";
// Récupère les identifiants des questions sélectionnées par le concepteur

$lists = $DB->get_records('knowledge_node_questions', array('instance' => $domoscio->id, 'knowledge_node' => $kn), '', '*');

if(!empty($lists))
{
    $random = array_rand($lists, 1);
    $selected = $DB->get_record('knowledge_node_questions', array('id' => $random), '*');

    if($selected->type == "scorm")
    {
        $scorm = $DB->get_record('scorm_scoes', array('id' => $selected->question_id), '*');
        $cm_selected = $scorm->scorm;
        $cap = 'mod/scorm:savetrack';
        $module = 18;
    }
    else
    {
        $slot = $DB->get_record('quiz_slots', array('questionid' => $selected->question_id), '*');
        $cm_selected = $slot->quizid;
        $cap = 'mod/quiz:view';
        $module = 16;
    }

    // Check student is allowed to see selected question
    $cmid = $DB->get_record('course_modules', array('instance' => $cm_selected, 'module' => $module));
    $cm_context = context_module::instance($cmid->id);

    if(has_capability($cap, $cm_context))
    {
        if($selected->type == "scorm")
        {
            $domoscioid = $temp = $cm->id;
            $a = $scorm->scorm;
            $scoid = $selected->question_id;
            $content = "<input type='hidden' value=$scoid name=scoid></input>";
            $sco_url = "$CFG->wwwroot/mod/scorm/player.php?a=$scorm->scorm&scoid=$selected->question_id&newattempt=on&display=popup";

            $scorm_frame = html_writer::tag('iframe', '', array('src' => $sco_url,
                                                                'width' => 1000,
                                                                'height' => 500,
                                                                'style' => 'border:none'));

            $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next'));
            $params = "id=$temp&scorm=".$a."&kn=$kn";

            $output = $scorm_frame.html_writer::tag('form', $content, array('method' => 'POST', 'action' => $url_r.'?'.$params, 'id' => 'responseform'));
            echo $output;
        }
        else
        {

            // Retrieve selected question data
            $question = $DB->get_record('question', array('id' => $selected->question_id), '*');
            $qinstance = "kn_q".$question->id;

            $content = html_writer::tag('input', '', array('type' => 'hidden', 'value' => $domoscio->id, 'name' => $qinstance))
                      .domoscio_display_questions($question, $selected->type);
            $params = "kn=$kn&q=$selected->question_id";

            $content .= html_writer::tag('input', '', array('type' => 'submit', 'value' => get_string('validate_btn', 'domoscio'), 'name' => 'next'));
            $output = html_writer::tag('form', $content, array('method' => 'POST', 'action' => $url_r.'?'.$params, 'id' => 'responseform'));
            echo $output;
        }
    }
    else
    {
        echo $OUTPUT->header();

        echo $OUTPUT->heading(get_string('test_session', 'domoscio'));

        echo get_string('nocapabilitytousethisservice', 'error');
    }
}
else
{
    echo html_writer::tag('blockquote', get_string('tests_empty', 'domoscio'), array('class' => 'muted'));
}

echo html_writer::tag('button', get_string('end_btn', 'domoscio'), array('type' => 'button',
                                                                       'onclick'=>"javascript:location.href='$CFG->wwwroot/mod/domoscio/results.php?end=true'"));

echo $OUTPUT->footer();
