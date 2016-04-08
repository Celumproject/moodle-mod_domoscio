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
 * Select notions view
 *
 * This view allow course creator to define notions to be reviewed
 * with this instance of Domoscio
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

$PAGE->requires->js('/mod/domoscio/jquery-1.11.3.min.js', true);
$PAGE->requires->js('/mod/domoscio/script.js', true);

$config = get_config('domoscio');
domoscio_check_settings($config);
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


// Print the page header.
$PAGE->set_url('/mod/domoscio/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($domoscio->name));
$PAGE->set_heading(get_string('pluginname', 'domoscio'));

echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading(get_string('def_notions', 'domoscio'));

$rest = new mod_domoscio_client();

$resource = json_decode($rest->seturl($config, 'knowledge_nodes', $domoscio->resourceid)->get());

if (has_capability('mod/domoscio:addinstance', $context)) {

    echo html_writer::tag('div', html_writer::tag('b', get_string('notions_intro', 'domoscio'), array('class' => 'mod_introbox')), array('class' => 'block'));
    $overviewurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/view.php?id='.$cm->id,
                                                       get_string('global_view', 'domoscio')
                                                      ),
                                     array('class' => ''));
    $defnotionurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/select_notions.php?id='.$cm->id,
                                                       get_string('def_notions', 'domoscio')
                                                      ),
                                     array('class' => 'active warning'));
    $showstatsurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/stats.php?id='.$cm->id,
                                                       get_string('stats', 'domoscio')
                                                      ),
                                     array('class' => ''));
    echo html_writer::tag('ul', $overviewurl.$defnotionurl.$showstatsurl, array('class' => 'nav nav-tabs'));

    $mform = new mod_domoscio_select_notion_form("$CFG->wwwroot/mod/domoscio/select_notions.php?id=$cm->id", array('instance' => $domoscio->id,
                                                                                                                     'parent' => $domoscio->resourceid));

    if ($mform->is_cancelled()) {

        redirect("$CFG->wwwroot/mod/domoscio/view.php?id=".$cm->id);
        exit;

    } else if ($fromform = $mform->get_data()) {

        foreach ($fromform as $k => $value) {
            if (is_numeric($k)) {
                $rest = new mod_domoscio_client();
                if ($value == 1) {
                    $entry = new stdClass;
                    $entry->id = $k;
                    $entry->active = 1;
                    $write = $DB->update_record('domoscio_knowledge_nodes', $entry, $bulk = false);
                    $kn = $DB->get_record('domoscio_knowledge_nodes', array('id' => $k));
                    $knodestudents = $DB->get_records('domoscio_knode_students', array('knodeid' => $kn->knodeid), '', '*');
                    foreach ($knodestudents as $kns) {
                        $json = json_encode(array('active' => 1));
                        $rest->seturl(get_config('domoscio'), 'knowledge_node_students', $kns->knodestudentid)->put($json);
                    }

                } else if ($value == 0) {
                    $entry = new stdClass;
                    $entry->id = $k;
                    $entry->active = 0;
                    $write = $DB->update_record('domoscio_knowledge_nodes', $entry, $bulk = false);
                    $kn = $DB->get_record('domoscio_knowledge_nodes', array('id' => $k));
                    $knodestudents = $DB->get_records('domoscio_knode_students', array('knodeid' => $kn->knodeid), '', '*');
                    foreach ($knodestudents as $kns) {
                        $json = json_encode(array('active' => 0));
                        $rest->seturl(get_config('domoscio'), 'knowledge_node_students', $kns->knodestudentid)->put($json);
                    }
                }
            }
        }

        redirect("$CFG->wwwroot/mod/domoscio/select_notions.php?id=".$cm->id);
        exit;

    } else {

        $mform->display();
    }

    $btn = html_writer::tag('button',
                            get_string('add_notion_btn', 'domoscio'),
                            array('type' => 'button',
                                  'id' => 'addnotion',
                                  'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/create_notion.php?id=$cm->id'")
                           );
    echo html_writer::tag('blockquote', '<small>'.get_string('add_notion_expl', 'domoscio').'</small>'.$btn, array('class' => 'muted'));
}

// Finish the page.
echo $OUTPUT->footer();
