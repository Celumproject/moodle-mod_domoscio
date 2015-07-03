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
 * More datas and stats from each students such as their name, the number
 * of succeeded and failed tests...
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');
require_once(dirname(__FILE__).'/classes/select_notion_form.php');

//$PAGE->requires->js('/mod/domoscio/jquery-1.11.3.min.js', true);
$PAGE->requires->js('/mod/domoscio/bootstrap-collapse.js', true);
$PAGE->requires->js('/mod/domoscio/Chart.min.js', true);

$config = get_config('domoscio');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('d', 0, PARAM_INT);  // ... domoscio instance ID - it should be named as the first character of the module.
$kn = optional_param('kn', 0, PARAM_INT); // Knowledge_node ID (Rappels)
$stat = optional_param('stat', '', PARAM_ALPHANUMEXT);

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = get_course($cm->course);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $domoscio  = $DB->get_record('domoscio', array('id' => $n), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = get_coursemodule_from_instance('domoscio', $domoscio->id, $course->id, false, MUST_EXIST);
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
echo $OUTPUT->heading($domoscio->name);

$rest = new domoscio_client();

$resource = json_decode($rest->setUrl($config, 'knowledge_nodes', $domoscio->resource_id)->get());

$linked_resource = get_resource_info($resource->id);

echo html_writer::tag('div', '<h5 class="content">'.get_string('stats', 'domoscio').'</h5>', array('class' => 'block'));
echo html_writer::link("$CFG->wwwroot/mod/domoscio/stats.php?id=$cm->id", '<< '.get_string('back_btn', 'domoscio')."<br/><br/>");

// --- TEACHER VIEW ---

if (user_has_role_assignment($USER->id,3)) {

    if($stat = 'students')
    {
        $students = get_stats($kn)->enrolled;

        $trows = "";

        foreach($students as $student)
        {
            $student_info = get_student_by_kns($student->id);
            $attempts = count(str_split($student->history));
            $right_attempts = count(array_filter(str_split($student->history)));
            $wrong_attempts = $attempts - $right_attempts;

            $trows .= html_writer::tag('tr', html_writer::tag('td', $student_info->firstname." ".$student_info->lastname).
                                             html_writer::tag('td', secondsToTime(strtotime($student->next_review_at) - time())).
                                             html_writer::tag('td', $right_attempts, array('class' => 'alert-success')).
                                             html_writer::tag('td', $wrong_attempts, array('class' => 'alert-danger'))
                                      );
        }
        $th = html_writer::tag('tr', html_writer::tag('th', get_string('student', 'domoscio')).
                                     html_writer::tag('th', get_string('next_due_th', 'domoscio')).
                                     html_writer::tag('th', "<i class='icon-ok'></i>".get_string('test_succeeded', 'domoscio'), array('class' => 'alert-success')).
                                     html_writer::tag('th', "<i class='icon-remove'></i>".get_string('test_failed', 'domoscio'), array('class' => 'alert-danger')));
        echo html_writer::tag('table', $th.$trows, array('class' => 'table table-striped table-bordered table-hover'));
    }

}

// Finish the page.
echo $OUTPUT->footer();
