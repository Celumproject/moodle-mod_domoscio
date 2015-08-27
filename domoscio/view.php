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
 * Show a particular instance of domoscio
 *
 * Here are listed next review dates for each notion and
 * knowledge stats are displayed.
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');
require_once($CFG->dirroot . '/mod/scorm/lib.php');

// $PAGE->requires->js('/mod/domoscio/jquery-1.11.3.min.js', true);
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

$rest = new mod_domoscio_client();

$resource = json_decode($rest->seturl($config, 'knowledge_nodes', $domoscio->resourceid)->get());

$linkedresource = domoscio_get_resource_info($resource->id);

if (has_capability('moodle/course:create', $context)) {
    // --- TEACHER VIEW ---

    $notions = $DB->get_records('domoscio_knowledge_nodes', array('instance' => $domoscio->id, 'active' => '1'), '', '*');

    $introbox = html_writer::tag('b', get_string('resource_assigned', 'domoscio'), array('class' => 'content')).
                html_writer::link($linkedresource->url, $linkedresource->display);
    echo html_writer::tag('div', $introbox, array('class' => 'block'));

    $overviewurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/view.php?id='.$cm->id,
                                                       get_string('global_view', 'domoscio')
                                                      ),
                                     array('class' => 'active'));
    $defnotionurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/select_notions.php?id='.$cm->id,
                                                       get_string('def_notions', 'domoscio')
                                                      ),
                                     array('class' => 'warning'));

    $showstatsurl = html_writer::tag('li',
                                     html_writer::link($CFG->wwwroot.'/mod/domoscio/stats.php?id='.$cm->id,
                                                       get_string('stats', 'domoscio')
                                                      ),
                                     array('class' => ''));
    $notionlist = "";
    foreach ($notions as $notion) {
        $renderq = '';
        $rest = new mod_domoscio_client();

        $title = json_decode($rest->seturl($config, 'knowledge_nodes', $notion->knodeid)->get());

        $qids = $DB->get_records_sql("SELECT *
                                        FROM {domoscio_knode_questions}
                                       WHERE knodeid= :knid",
                                     array('knid' => $notion->knodeid)
                                    );

        foreach ($qids as $qid) {
            if ($qid->type == "scorm") {
                $sco = $DB->get_record('scorm_scoes', array('id' => $qid->questionid), '*');

                $renderq .= html_writer::tag('b', $linkedresource->display)." - ".$sco->title.html_writer::tag('hr', '');
            } else {
                if ($qid->type == "quiz") {
                    $question = $DB->get_record('question', array('id' => $qid->questionid), '*');
                    $questionname = $question->name;
                    $questiontext = $question->questiontext;
                } else if ($qid->type == "lesson") {
                    $question = $DB->get_record('lesson_pages', array('id' => $qid->questionid), '*');
                    $questionname = $question->title;
                    $questiontext = $question->contents;
                }

                $renderq .= html_writer::tag('b', $questionname." : ").strip_tags($questiontext).html_writer::tag('hr', '');
            }
        }
        $accordioninner = html_writer::tag('div', $renderq, array('class' => 'accordion-inner'));
        $accordioncollapse = html_writer::tag('div', $accordioninner, array('class' => 'accordion-body collapse', 'id' => 'collapse-'.$notion->id));
        $togglers = html_writer::link('#collapse-'.$notion->id, html_writer::start_span('content').
                                                                html_writer::tag('i', '', array('class' => 'icon-chevron-down')).
                                                                " $title->name (".count($qids)." question".domoscio_plural($qids).")".
                                                                html_writer::end_span(), array(
                                                                                            'class' => 'accordion-toggle',
                                                                                            'data-toggle' => 'collapse',
                                                                                            'data-parent' => '#accordion2'));
        $togglers .= html_writer::link($CFG->wwwroot.'/mod/domoscio/linkto.php?id='.$cm->id.'&notion='.$notion->knodeid,
                                       html_writer::tag('button',
                                                        get_string('choose_q', 'domoscio'),
                                                        array('type' => 'button', 'class' => 'btn btn-link pull-right')));
        $accordionheading = html_writer::tag('div', $togglers, array('class' => 'well well-small' , 'style' => 'margin-bottom:0px'));
        $accordiongroup = html_writer::tag('div', $accordionheading.$accordioncollapse, array('class' => 'accordion-group'));
        $notionlist .= html_writer::tag('div', $accordiongroup, array('class' => 'accordion', 'id' => 'accordion'));
    }

    if (empty($notions)) {
        $notionlist = html_writer::tag('blockquote', get_string('notions_empty', 'domoscio'), array('class' => 'muted'));
    }

    echo html_writer::tag('ul', $overviewurl.$defnotionurl.$showstatsurl, array('class' => 'nav nav-tabs')).
         html_writer::tag('div', '<h6 class="lead muted text-center">'.get_string('set_notions', 'domoscio').'</h6>'.$notionlist, array('class' => 'coursebox header'));


} else if (has_capability('mod/domoscio:submit', $context)) {
    // --- STUDENT VIEW ---
    // Check if student already logged up the Domoscio plugin
    $check = $DB->get_record('domoscio_userapi', array('userid' => $USER->id), '*');

    if (empty($check)) {
        // If not, plugin calls API for creating new student profile
        domoscio_create_student();

        echo html_writer::tag('div',
                              html_writer::tag('h5',
                                               get_string('welcome', 'domoscio').$USER->firstname,
                                               array('class' => 'content')),
                              array('class' => 'block'));
        echo get_string('student_first_visit', 'domoscio')."<br/>";
        echo html_writer::tag('button',
                              get_string('start_btn', 'domoscio'),
                              array('type' => 'button',
                                  'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id'"));
    } else {
        // If true, retrive student datas
        $knstudent = domoscio_manage_student($config, $domoscio, $check);

        $count = domoscio_count_tests($config);
        $url2 = new moodle_url("$CFG->wwwroot/mod/domoscio/index.php");

        if (!empty($count)) {
            $indexurl = $OUTPUT->action_link( $url2, get_string('do_review_btn', 'domoscio'));
        } else {
            $indexurl = '';
        }

        $todocounter = html_writer::start_span('badge badge-important').
                       html_writer::tag('h4', count($count)).
                       html_writer::end_span().get_string('text2', 'domoscio').
                       domoscio_plural($count).get_string('text3', 'domoscio')." ";

        $introbox = html_writer::tag('div', $todocounter.$indexurl, array('class' => 'content'))."<hr/>".
                    html_writer::tag('b', get_string('reviewed', 'domoscio'), array('class' => 'content')).
                    html_writer::link($linkedresource->url, $linkedresource->display).
                    html_writer::tag('hr', '');

        $divcanvas = html_writer::tag('canvas', '', array('id' => 'polarChart'));
        $statsheader = html_writer::tag('p', get_string('stats', 'domoscio'), array('class' => 'content'));


        if (!empty($check) && $knstudent) {
            $SESSION->todo = $SESSION->results = $SESSION->no_history = array();
            $knstats = array();

            $accordion = "";
            foreach ($knstudent as $notion) {
                $item = json_decode($rest->seturl($config, 'knowledge_nodes', $notion->knowledge_node_id)->get());
                $SESSION->todo[] = $item->id;
                $knstatsobj = new stdClass;
                $knstatsobj->item = $item;
                $knstatsobj->knstudent = $notion;
                $knstats[] = $knstatsobj;

                if ($notion->next_review_at == null) {
                    $reminder = html_writer::tag('button',
                                                 get_string('do_test', 'domoscio'),
                                                 array('type' => 'button',
                        'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=$notion->knowledge_node_id"."&t=".time()."'"));
                    $accordioninner = html_writer::tag('div', get_string('no_history', 'domoscio').$reminder, array('class' => 'accordion-inner'));
                    $alerticon = " > ".html_writer::tag('i', '', array('class' => 'icon-exclamation-sign'));
                    $class = "alert-warning";
                    $SESSION->no_history[] = $item->id;
                } else {
                    if (strtotime($notion->next_review_at) < time()) {
                        $alerticon = " > ".html_writer::tag('i', '', array('class' => 'icon-edit'));
                        $class = "alert-danger";
                        $btntest = html_writer::tag('button',
                                                    get_string('do_review_btn', 'domoscio'),
                                                    array('type' => 'button',
                        'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=$notion->knowledge_node_id&solo=true&t=".time()."'",
                                                         'class' => 'btn btn-danger'));
                    } else {
                        $alerticon = '';
                        $class = "";
                        $btntest = "";
                    }
                    $reminder = date('d/m/Y '.get_string('at', 'domoscio').' H:i', strtotime($notion->next_review_at));
                    $accordioninner = html_writer::tag('div', get_string('next_due', 'domoscio').$reminder." ".$btntest, array('class' => 'accordion-inner'));
                }


                $accordioncollapse = html_writer::tag('div', $accordioninner, array('class' => 'accordion-body collapse', 'id' => 'collapse-'.$notion->id));
                $togglers = html_writer::link('#collapse-'.$notion->id, html_writer::start_span('content').
                                                                      html_writer::tag('i', '', array('class' => 'icon-chevron-down'))." ".
                                                                      $item->name.$alerticon.
                                                                      html_writer::end_span(), array(
                                                                                                  'class' => 'accordion-toggle',
                                                                                                  'data-toggle' => 'collapse',
                                                                                                  'data-parent' => '#accordion'));
                $accordionheading = html_writer::tag('div', $togglers, array('class' => 'well well-small '.$class , 'style' => 'margin-bottom:0px'));
                $accordiongroup = html_writer::tag('div', $accordionheading.$accordioncollapse, array('class' => 'accordion-group'));
                $accordion .= html_writer::tag('div', $accordiongroup, array('class' => 'accordion', 'id' => 'accordion'));
            }

        }

        if ($SESSION->no_history != null) {
            $testbtn = html_writer::tag('button',
                                         get_string('do_test', 'domoscio'),
                                         array('type' => 'button',
                                            'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".
                                                         array_shift($SESSION->no_history)."&t=".time()."&first=1'",
                                              'class' => 'btn btn-primary'));
        } else {
            $testbtn = "";
        }

        $startbtn = html_writer::tag('button',
                                      get_string('do_training', 'domoscio'),
                                      array('type' => 'button',
                                         'onclick' => "javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".
                                                      array_shift($SESSION->todo)."&t=".time()."'",
                                           'class' => 'btn btn-warning'));

        $row = html_writer::tag('div', $introbox.$testbtn.$startbtn, array('class' => 'block span6')).
               html_writer::tag('div', $statsheader . $divcanvas, array('class' => 'block span6'));

        echo html_writer::tag('div', $row, array('class' => 'row mod_introbox', 'style' => 'margin-left:-10px'));

        echo $accordion;
    }

?>
<script type="text/javascript">
    var ctx = document.getElementById("polarChart").getContext("2d");

    var data = [];
    <?php
    foreach ($knstats as $notion) {
        $laps = (strtotime($notion->knstudent->next_review_at) - time());
        if ($laps >= 1296000) {
            $color = "#01DF01";
            $highlight = "#40FF00";
            $value = 1;
        } else if ($laps < 1296000 && $laps >= 604800) {
            $color = "#BFFF00";
            $highlight = "#D0FA58";
            $value = ($laps / 2592000) + 0.3;
        } else if ($laps < 604800 && $laps >= 86400) {
            $color = "#F7FE2E";
            $highlight = "#FFFF00";
            $value = ($laps / 2592000) + 0.2;
        } else {
            $color = "#F7464A";
            $highlight = "#FF0000";
            $value = 0.2;
        }
        echo 'var obj = {value: '.$value.', color:"'.$color.'", highlight: "'.$highlight.'", label: "'.$notion->item->name.'"};';
        echo 'data.push(obj);';
    }
    ?>
    var options = {
        scaleShowLine : true,
        scaleShowLabels: false,
        scaleOverride : true,
        scaleSteps : 10,
        scaleStepWidth : 0.1,
        scaleStartValue : 0,
        align : 'center',
        responsive : true,
        tooltipTemplate: "<%if (label){%><%=label%><%}%>"
    };
    var historyChart = new Chart(ctx).PolarArea(data, options);
</script>
<?php
}

echo $OUTPUT->footer();
