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
 * This view indexes all to do tests and useful shortlinks for student
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');
require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/user/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID
$instance = optional_param('instance', 0, PARAM_INT); // Course_module ID
$activate = optional_param('activate', null, PARAM_RAW);
$student = optional_param('student', null, PARAM_INT);
$sort    = optional_param('sort', 'name', PARAM_ALPHANUM);

if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
} else if ($instance) {
    $cm = get_coursemodule_from_instance('domoscio', $instance, 0, false, MUST_EXIST);
}
$course     = get_course($cm->course);
$domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);

require_login();
$config = get_config('domoscio');
$context = context_course::instance($course->id);
$PAGE->set_context($context);

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php');
$PAGE->navbar->add($strname);
$PAGE->set_title(get_string('pluginname', 'domoscio'));
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_pagelayout('incourse');

$rest = new mod_domoscio_client();
$cache = cache::make_from_params(cache_store::MODE_SESSION, 'mod_domoscio', 'cache');

echo $OUTPUT->header();
domoscio_check_settings($config);

if (has_capability('mod/domoscio:addinstance', $context)) {
    // --- TEACHER VIEW ---
    if ($student) {
        if ($activate == "false") {
            $json = json_encode(array('id' => $student, 'active' => boolval(false)));

            $update = $rest->seturl($config, 'students', $student)->put($json);
        } else if ($activate == "true") {
            $json = json_encode(array('id' => $student, 'active' => boolval(true)));

            $update = $rest->seturl($config, 'students', $student)->put($json);
        }
    }
    $returnurl = new moodle_url('index.php'/*, array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage, 'page'=>$page)*/);
    echo $OUTPUT->heading(get_string('students_list', 'domoscio'));

    $students = $DB->get_records_sql("SELECT userid, userapiid
                                        FROM {domoscio_knode_students}
                                    GROUP BY userid");

    $fullnamedisplay = array();

    $table = new html_table();
    $table->head = array();
    $table->colclasses = array();
    $table->attributes['class'] = 'admintable generaltable';
    $table->head[] = "Name";
    $table->head[] = "Last activity";
    $table->head[] = "Activation";

    foreach($students as $student){
        $row = $buttons = array();
        $user = $DB->get_record('user', array('id' => $student->userid), '*');
        $studentapi = json_decode($rest->seturl($config, 'students', $student->userapiid)->get());
        if($studentapi[0]->last_activity) {
            $date = new DateTime($studentapi[0]->last_activity);
            $date = $date->format('d/m/Y H:i');
        } else {
            $date = "";
        }

        if($studentapi[0]->active == true) {
            $buttons[] = html_writer::link(new moodle_url($returnurl, array('activate' => "false", 'student' => $student->userapiid)), "Désactiver");
        } else {
            $buttons[] = html_writer::link(new moodle_url($returnurl, array('activate' => "true", 'student' => $student->userapiid)), "Activer");
        }

        $row[] = $user->firstname." ".$user->lastname;
        $row[] = $date;
        $row[] = implode(' ', $buttons);
        $table->data[] = $row;
    }

    echo html_writer::table($table);


} else if (has_capability('mod/domoscio:submit', $context, $USER)) {
    // --- STUDENT VIEW ---
    echo $OUTPUT->heading(get_string('desk', 'domoscio'));

    $date = usergetdate(time());
    list($d, $m, $y, $h, $min) = array($date['mday'], $date['mon'], $date['year'], $date['hours'], $date['minutes']);

    $tsobj = new mod_domoscio_test_session();
    $tsobj->fetch_all_user_tests();
    $cache->set('test_session', $tsobj);

    $check = $DB->get_record('domoscio_userapi', array('userid' => $USER->id), '*');
    $student = json_decode($rest->seturl($config, 'students', $check->uniqid)->get());
    $displayuser = html_writer::tag('p', get_string('welcome', 'domoscio').$student[0]->id);
    $notification = userdate(make_timestamp($y, $m, $d, $h, $min))."<br/>".
                    html_writer::start_span('badge badge-important').
                    html_writer::tag('h4', count($tsobj->get_list())).
                    html_writer::end_span().get_string('text2', 'domoscio').domoscio_plural($tsobj->get_list()).get_string('text3', 'domoscio');

    $table = new html_table();
    $table->head = array();
    $table->colclasses = array();
    $table->attributes['class'] = 'table table-bordered table-hover';
    $table->head[] = get_string('module', 'domoscio');
    $table->head[] = get_string('do_test', 'domoscio');
    $table->head[] = get_string('see_notion', 'domoscio');
    $table->head[] = get_string('stats', 'domoscio');

    $getlist = $tsobj->get_list();
    if (!empty($getlist)) {
        $todo = $tsobj->get_list();
        $rest = new mod_domoscio_client();
        foreach ($tsobj->get_list() as $test) {
            $row = new html_table_row();
            $resource = $test->get_kn_data();
            $kninfo = $test->get_item();

            $row->cells[] = $resource->display." - ".$kninfo->name;
            $row->cells[] = html_writer::link($CFG->wwwroot.'/mod/domoscio/doquiz.php?kn='.$test->get_related_kn().'&solo=true&t=true',
                                                     html_writer::tag('i', '', array('class' => 'icon-edit icon-white')),
                                                     array('target' => '_blank',
                                                            'class' => 'btn btn-danger'));
            $row->cells[] = html_writer::link($resource->url,
                                                     html_writer::tag('i', '', array('class' => 'icon-book icon-white')),
                                                     array('target' => '_blank',
                                                           'class' => 'btn btn-primary'));
            $row->cells[] = html_writer::link($CFG->wwwroot.'/mod/domoscio/view.php?d='.$test->get_related_domoscio()->instance,
                                                     html_writer::tag('i', '', array('class' => 'icon-signal icon-white')),
                                                     array('target' => '_blank',
                                                           'class' => 'btn btn-success'));

            $table->data[] = $row;
        }


        $startbtn = html_writer::tag('button',
                              get_string('start_tests', 'domoscio'),
                              array('type' => 'button',
                                    'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".array_shift($todo)->get_related_kn()."&t=true'"));

        echo html_writer::tag('div', html_writer::tag('h5', $displayuser."<hr/>".$notification.$startbtn, array('class' => 'content')), array('class' => 'block'));
        echo html_writer::table($table);
    } else {
        echo get_string('no_test', 'domoscio');
    }
}
echo $OUTPUT->footer();
