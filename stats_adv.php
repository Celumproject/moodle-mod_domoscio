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
 * Advanced stats view
 *
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

//$PAGE->requires->js('/mod/domoscio/jquery-1.11.3.min.js', true);
$PAGE->requires->js('/mod/domoscio/bootstrap-collapse.js', true);
$PAGE->requires->js('/mod/domoscio/Chart.min.js', true);

$config = get_config('domoscio');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('d', 0, PARAM_INT);  // ... domoscio instance ID - it should be named as the first character of the module.
$kn = optional_param('kn', 0, PARAM_INT); // Knowledge_node ID (Rappels)
$pagenum = optional_param('page', 1, PARAM_INT); // Student list pagination
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
    print_error('missingparameter');
}

$context = context_module::instance($cm->id);
require_login($course, true, $cm);

$PAGE->set_url('/mod/domoscio/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($domoscio->name));
$PAGE->set_heading(get_string('pluginname', 'domoscio'));

echo $OUTPUT->header();
echo $OUTPUT->heading($domoscio->name);
domoscio_check_settings($config);

$rest = new mod_domoscio_client();

$resource = json_decode($rest->seturl($config, 'knowledge_nodes', $kn)->get());

$linkedresource = domoscio_get_resource_info($kn);

echo html_writer::tag('div', '<h5 class="content">'.
                             get_string('stats', 'domoscio')." ".
                             $linkedresource->display." - ".
                             $resource->name.'</h5>', array('class' => 'block'));

// --- TEACHER VIEW ---

if (has_capability('mod/domoscio:addinstance', $context)) {
    // Display teacher dashboard tabs
    $overviewurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/view.php?id='.$cm->id,
                                                       get_string('global_view', 'domoscio')
                                                      ),
                                     array('class' => ''));
    $defnotionurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/select_notions.php?id='.$cm->id,
                                                       get_string('def_notions', 'domoscio')
                                                      ),
                                     array('class' => 'warning'));
    $showstatsurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/stats.php?id='.$cm->id,
                                                       get_string('stats', 'domoscio')
                                                      ),
                                     array('class' => 'active'));
    echo html_writer::tag('ul', $overviewurl.$defnotionurl.$showstatsurl, array('class' => 'nav nav-tabs'));
    echo html_writer::link("$CFG->wwwroot/mod/domoscio/stats.php?id=$cm->id", '<< '.get_string('back_btn', 'domoscio')."<br/><br/>");

    // Prepare table datas and pagination
    if ($stat = 'students') {
        $limit = 10;
        $offset = max(($pagenum - 1), 0) * $limit;
        $students = domoscio_get_stats($kn, $offset, $limit)->enrolled;
        $nextpage = $pagenum + 1;
        $prevpage = $pagenum - 1;
        $pagestotal = ceil((domoscio_get_stats($kn)->count_students) / $limit);

        $table = new html_table();
        $table->head = array();
        $table->attributes['class'] = 'table table-bordered table-hover table-striped';
        $table->head[] = get_string('student', 'domoscio');
        $table->head[] = get_string('next_due_th', 'domoscio');
        $headcell1 = new html_table_cell(html_writer::tag('i', '', array('class' => 'icon-ok')).get_string('test_succeeded', 'domoscio'));
        $headcell1->attributes['class'] = 'alert-success';
        $table->head[] = $headcell1;
        $headcell2 = new html_table_cell(html_writer::tag('i', '', array('class' => 'icon-remove')).get_string('test_failed', 'domoscio'));
        $headcell2->attributes['class'] = 'alert-danger';
        $table->head[] = $headcell2;

        foreach ($students as $student) {
            $studentinfo = domoscio_get_student_by_kns($student->id);
            $attempts = count(array_filter(str_split($student->history), 'strlen'));
            $rightattempts = count(array_filter(str_split($student->history)));
            $wrongattempts = $attempts - $rightattempts;
            $row = new html_table_row();


            // Add stats to display for each students
            $row->cells[] = $studentinfo->firstname." ".$studentinfo->lastname;
            $row->cells[] = domoscio_sec_to_time(strtotime($student->next_review_at) - time());
            $cell1 = new html_table_cell(html_writer::tag('div', $rightattempts));
            $cell1->attributes['class'] = 'alert-success';
            $row->cells[] = $cell1;
            $cell2 = new html_table_cell(html_writer::tag('div', $wrongattempts));
            $cell2->attributes['class'] = 'alert-danger';
            $row->cells[] = $cell2;
            $table->data[] = $row;
        }

        // Display stats table
        echo html_writer::table($table);

        // Display pagination
        $statsurl = new moodle_url("$CFG->wwwroot/mod/domoscio/stats_adv.php");
        $statsurl->param('id', $cm->id);
        $statsurl->param('kn', $kn);
        $statsurl->param('stat', "students");

        if ($pagestotal > 1) {
            if ($pagenum > 1) {
                $statsurl->param('page', $prevpage);
                echo html_writer::link($statsurl, get_string('prev_page', 'domoscio'));
            }
            echo html_writer::tag('p', 'Page '.$pagenum.'/'.$pagestotal);
            if ($pagenum < $pagestotal) {
                $statsurl->param('page', $nextpage);
                echo html_writer::link($statsurl, get_string('next_page', 'domoscio'));
            }
        }
    }
}

echo $OUTPUT->footer();
