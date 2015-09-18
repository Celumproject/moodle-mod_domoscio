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

$config = get_config('domoscio');
require_login();
$context = context_system::instance();
$PAGE->set_context($context);

$strname = get_string('modulename', 'mod_domoscio');
$PAGE->set_url('/mod/domoscio/index.php');
$PAGE->navbar->add($strname);
$PAGE->set_title(get_string('pluginname', 'domoscio'));
$PAGE->set_heading(get_string('pluginname', 'domoscio'));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('desk', 'domoscio'));

if (has_capability('mod/domoscio:submit', $context)) {
    $date = usergetdate(time());
    list($d, $m, $y, $h, $min) = array($date['mday'], $date['mon'], $date['year'], $date['hours'], $date['minutes']);

    $todotests = domoscio_count_tests($config);
    $rest = new mod_domoscio_client();

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
