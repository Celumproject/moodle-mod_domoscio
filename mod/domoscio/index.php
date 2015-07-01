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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');

$config = get_config('domoscio');
$PAGE->set_context(context_system::instance());
require_login();

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php');
$PAGE->navbar->add($strname);
$PAGE->set_title(format_string("Rappels"));
$PAGE->set_heading("Domoscio for Moodle");
$PAGE->set_pagelayout('incourse');

$date = usergetdate(time());
list($d, $m, $y, $h, $min) = array($date['mday'], $date['mon'], $date['year'], $date['hours'], $date['minutes']);

echo $OUTPUT->header();

$todo_tests = count_tests($config);
$rest = new domoscio_client();

echo $OUTPUT->heading(get_string('desk', 'domoscio'));

$check = $DB->get_record('userapi', array('user_id' => $USER->id), '*');
$student = json_decode($rest->setUrl($config, 'students', $check->uniq_id)->get());

$display_user = html_writer::tag('p', get_string('welcome', 'domoscio').$student->id);
$notification = userdate(make_timestamp($y, $m, $d, $h, $min))."<br/>".html_writer::start_span('badge badge-important').html_writer::tag('h4', count($todo_tests)).html_writer::end_span().get_string('text2', 'domoscio').plural($todo_tests).get_string('text3', 'domoscio');
echo html_writer::tag('div', html_writer::tag('h5', $display_user."<hr/>".$notification, array('class' => 'content')), array('class' => 'block'));

if(!empty($todo_tests))
{
  $_SESSION['todo'] = array();
  $trows = "";
  $rest = new domoscio_client();
  foreach($todo_tests as $kn)
  {
    $resource = get_resource_info($kn);
    $domoscio_id = $DB->get_record('knowledge_nodes', array('knowledge_node_id' => $kn), '*');
    $_SESSION['todo'][] = $kn;

    $kn_info = json_decode($rest->setUrl($config, 'knowledge_nodes', $kn)->get());

    $trows .= html_writer::tag('tr', html_writer::tag('td', $resource->display." - ".$kn_info->name).
                                     html_writer::tag('td', html_writer::link($CFG->wwwroot.'/mod/domoscio/doquiz.php?kn='.$kn.'&solo=true&t='.time(),
                                                                              '<i class="icon-edit icon-white"></i>',
                                                                              array('target' => '_blank',
                                                                                     'class' => 'btn btn-danger')), array('style' => 'text-align:center')).
                                     html_writer::tag('td', html_writer::link($resource->url,
                                                                              '<i class="icon-book icon-white"></i>', array('target' => '_blank',
                                                                                                                             'class' => 'btn btn-primary')), array('style' => 'text-align:center')).
                                     html_writer::tag('td', html_writer::link($CFG->wwwroot.'/mod/domoscio/view.php?d='.$domoscio_id->instance,
                                                                              "<i class='icon-signal icon-white'></i>", array('target' => '_blank',
                                                                                                                               'class' => 'btn btn-success')), array('style' => 'text-align:center'))
                              );
  }
  $th = html_writer::tag('tr', html_writer::tag('th', get_string('module', 'domoscio')).
                               html_writer::tag('th', get_string('do_test', 'domoscio')).
                               html_writer::tag('th', get_string('see_notion', 'domoscio')).
                               html_writer::tag('th', get_string('stats', 'domoscio')));
  echo html_writer::tag('table', $th.$trows, array('class' => 'table table-striped table-bordered table-hover'));

  echo html_writer::tag('button', get_string('start_tests', 'domoscio'), array('type' => 'button','onclick'=>"javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".array_shift($_SESSION['todo'])."&t=".time()."'"));
}

else {echo get_string('no_test', 'domoscio');}

echo $OUTPUT->footer();
