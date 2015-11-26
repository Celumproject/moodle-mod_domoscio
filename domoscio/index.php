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

$activate = optional_param('activate', null, PARAM_RAW);
$student = optional_param('student', null, PARAM_INT);
$sort    = optional_param('sort', 'name', PARAM_ALPHANUM);

require_login();
$config = get_config('domoscio');
$context = context_system::instance();
$PAGE->set_context($context);

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php');
$PAGE->navbar->add($strname);
$PAGE->set_title(get_string('pluginname', 'domoscio'));
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_pagelayout('incourse');

$rest = new mod_domoscio_client();
echo $OUTPUT->header();
domoscio_check_settings($config);

if (has_capability('moodle/course:create', $context)) {
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


} else if (has_capability('mod/domoscio:submit', $context)) {
    // --- STUDENT VIEW ---
    echo $OUTPUT->heading(get_string('desk', 'domoscio'));

    $date = usergetdate(time());
    list($d, $m, $y, $h, $min) = array($date['mday'], $date['mon'], $date['year'], $date['hours'], $date['minutes']);

    $todotests = domoscio_count_tests($config);

    $check = $DB->get_record('domoscio_userapi', array('userid' => $USER->id), '*');
    $student = json_decode($rest->seturl($config, 'students', $check->uniqid)->get());
    $displayuser = html_writer::tag('p', get_string('welcome', 'domoscio').$student[0]->id);
    $notification = userdate(make_timestamp($y, $m, $d, $h, $min))."<br/>".
                    html_writer::start_span('badge badge-important').
                    html_writer::tag('h4', count($todotests)).
                    html_writer::end_span().get_string('text2', 'domoscio').domoscio_plural($todotests).get_string('text3', 'domoscio');

    $table = "";

    if (!empty($todotests)) {
        $SESSION->todo = array();
        $trows = "";
        $rest = new mod_domoscio_client();
        foreach ($todotests as $kn) {
            $resource = domoscio_get_resource_info($kn);
            $domoscioid = $DB->get_record('domoscio_knowledge_nodes', array('knodeid' => $kn), '*');
            $SESSION->todo[] = $kn;

            $kninfo = json_decode($rest->seturl($config, 'knowledge_nodes', $kn)->get());

            $trows .= html_writer::tag('tr', html_writer::tag('td', $resource->display." - ".$kninfo->name).
                                             html_writer::tag('td', html_writer::link($CFG->wwwroot.'/mod/domoscio/doquiz.php?kn='.$kn.'&solo=true&t='.time(),
                                                                                      '<i class="icon-edit icon-white"></i>',
                                                                                      array('target' => '_blank',
                                                                                             'class' => 'btn btn-danger')),
                                                                                      array('style' => 'text-align:center')).
                                             html_writer::tag('td', html_writer::link($resource->url,
                                                                                      '<i class="icon-book icon-white"></i>',
                                                                                      array('target' => '_blank',
                                                                                            'class' => 'btn btn-primary')),
                                                                                      array('style' => 'text-align:center')).
                                             html_writer::tag('td', html_writer::link($CFG->wwwroot.'/mod/domoscio/view.php?d='.$domoscioid->instance,
                                                                                      "<i class='icon-signal icon-white'></i>",
                                                                                      array('target' => '_blank',
                                                                                            'class' => 'btn btn-success')),
                                                                                      array('style' => 'text-align:center'))
                                      );
        }

        $startbtn = html_writer::tag('button',
                              get_string('start_tests', 'domoscio'),
                              array('type' => 'button',
                                    'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".array_shift($SESSION->todo)."&t=".time()."'"));

        $th = html_writer::tag('tr', html_writer::tag('th', get_string('module', 'domoscio')).
                                   html_writer::tag('th', get_string('do_test', 'domoscio')).
                                   html_writer::tag('th', get_string('see_notion', 'domoscio')).
                                   html_writer::tag('th', get_string('stats', 'domoscio')));

        echo html_writer::tag('div', html_writer::tag('h5', $displayuser."<hr/>".$notification.$startbtn, array('class' => 'content')), array('class' => 'block'));
        echo html_writer::tag('table', $th.$trows, array('class' => 'table table-striped table-bordered table-hover'));
    } else {
        echo get_string('no_test', 'domoscio');
    }
}
echo $OUTPUT->footer();
