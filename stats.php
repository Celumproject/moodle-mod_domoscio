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
 * Stats view
 *
 * Displays useful datas for course creator and teachers such as
 * the number of students enrolled for this instance, the success rate,
 * the to do tests count, etc...
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');

$PAGE->requires->js('/mod/domoscio/bootstrap-collapse.js', true);
$PAGE->requires->js('/mod/domoscio/Chart.min.js', true);

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
    $domoscio   = $DB->get_record('domoscio', array('resourceid' => $kn), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = $DB->get_record('course_modules', array('instance' => $domoscio->id, 'module' => $module->id), '*', MUST_EXIST);
    $id         = $cm->id;
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

$resource = json_decode($rest->seturl($config, 'knowledge_nodes', $domoscio->resourceid)->get());

$linkedresource = domoscio_get_resource_info($resource->id);

// --- TEACHER VIEW ---

if (has_capability('mod/domoscio:addinstance', $context)) {
    $notions = $DB->get_records('domoscio_knowledge_nodes', array('instance' => $domoscio->id, 'active' => '1'), '', '*');

    $introbox = html_writer::tag('b', get_string('resource_assigned', 'domoscio'), array('class' => 'content')).
                html_writer::link($linkedresource->url, $linkedresource->display);
    echo html_writer::tag('div', $introbox, array('class' => 'block'));
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

    foreach ($notions as $notion) {
        $rest = new mod_domoscio_client();

        $title = json_decode($rest->seturl($config, 'knowledge_nodes', $notion->knodeid)->get());

        $stat = domoscio_get_stats($notion->knodeid);

        if (empty($stat)) {
            $blocks = html_writer::tag('div', get_string('no_stats', 'domoscio'), array('class' => 'content span12'));
        } else {
            $enrolledstat = html_writer::tag('div', get_string('enrol_students', 'domoscio'), array('class' => 'content')).
                            html_writer::tag('h2', $stat->count_students, array('class' => 'text-center'));
            $gloablsuccess = html_writer::tag('div', get_string('avr_rate', 'domoscio'), array('class' => 'content')).
                             html_writer::tag('h2', $stat->global_success."%", array('class' => 'text-center'));
            $attempts = html_writer::tag('div', get_string('test_done', 'domoscio'), array('class' => 'content')).
                        html_writer::tag('h2', $stat->attempts, array('class' => 'text-center'));
            $todo = html_writer::tag('div', get_string('test_todo', 'domoscio'), array('class' => 'content')).
                    html_writer::tag('h2', $stat->todo, array('class' => 'text-center'));

            $blocks = html_writer::tag('div',
                                       $enrolledstat.
                                       html_writer::link("$CFG->wwwroot/mod/domoscio/stats_adv.php?id=$cm->id&kn=$notion->knodeid&stat=students",
                                                                get_string('stats_adv', 'domoscio'),
                                                                array('class' => 'content text-center')),
                                       array('class' => 'block span3'));
            $blocks .= html_writer::tag('div', $gloablsuccess, array('class' => 'block span3'));
            $blocks .= html_writer::tag('div', $attempts, array('class' => 'block span3'));
            $blocks .= html_writer::tag('div', $todo, array('class' => 'block span3'));

        }

        $row = html_writer::tag('div', $blocks, array('class' => 'row', 'style' => 'margin:0'));
        $accordioninner = html_writer::tag('div', $row, array('class' => 'accordion-inner'));
        $accordioncollapse = html_writer::tag('div', $accordioninner, array('class' => 'accordion-body collapse', 'id' => 'collapse-'.$notion->id));
        $togglers = html_writer::link('#collapse-'.$notion->id, html_writer::start_span('content').
                                                                html_writer::tag('i', '', array('class' => 'icon-chevron-down')).
                                                                " $title->name".
                                                                html_writer::end_span(), array(
                                                                                            'class' => 'accordion-toggle',
                                                                                            'data-toggle' => 'collapse',
                                                                                            'data-parent' => '#accordion2'));
        $accordionheading = html_writer::tag('div', $togglers, array('class' => 'well well-small' , 'style' => 'margin-bottom:0px'));
        $accordiongroup = html_writer::tag('div', $accordionheading.$accordioncollapse, array('class' => 'accordion-group'));
        echo $accordion = html_writer::tag('div', $accordiongroup, array('class' => 'accordion', 'id' => 'accordion'));
    }

    if (empty($notions)) {
        echo html_writer::tag('blockquote', get_string('notions_empty', 'domoscio'), array('class' => 'muted'));
    }
}

echo $OUTPUT->footer();
