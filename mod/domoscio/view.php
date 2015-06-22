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
 * Prints a particular instance of domoscio
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_domoscio
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace domoscio with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/sdk/client.php');
require_once(dirname(__FILE__).'/classes/select_notion_form.php');

$PAGE->requires->js('/mod/domoscio/jquery-1.11.3.min.js', true);
$PAGE->requires->js('/mod/domoscio/bootstrap-collapse.js', true);

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
    $domoscio   = $DB->get_record('domoscio', array('resource_id' => $kn), '*', MUST_EXIST);
    $course     = get_course($domoscio->course);
    $cm         = $DB->get_record('course_modules', array('instance' => $domoscio->id, 'module' => $module->id), '*', MUST_EXIST);
    $id         = $cm->id;
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

// Conditions to show the intro can change to look for own settings or whatever.
if ($domoscio->intro) {
    echo $OUTPUT->box(format_module_intro('domoscio', $domoscio, $cm->id), 'generalbox mod_introbox', 'domosciointro');
}

$rest = new domoscio_client();

$resource = json_decode($rest->setUrl($config, 'knowledge_nodes', $domoscio->resource_id)->get());

$linked_resource = get_resource_info($resource->id);

// --- TEACHER VIEW ---

if (user_has_role_assignment($USER->id,3)) {

    $notions = $DB->get_records('knowledge_nodes', array('instance' => $domoscio->id, 'active' => '1'), '', '*');

    $introbox = html_writer::tag('b', get_string('resource_assigned', 'domoscio'), array('class' => 'mod_introbox')).
                html_writer::link($linked_resource->url, $linked_resource->display);
    echo html_writer::tag('div', $introbox, array('class' => 'block'));

    $def_notion_link = html_writer::link($CFG->wwwroot.'/mod/domoscio/select_notions.php?id='.$cm->id, html_writer::tag('button', '1. '.get_string('def_notions', 'domoscio'), array('class' => 'btn btn-primary btn-large')),
                        array('class' => 'span4'));

    echo html_writer::tag('div', $def_notion_link.'<br/><br/>').html_writer::tag('div', '<h6>Les notions que vous avez définies :</h6><hr/>');

    foreach($notions as $notion)
    {
        $render_q = '';
        $rest = new domoscio_client();

        $title = json_decode($rest->setUrl($config, 'knowledge_nodes', $notion->knowledge_node_id)->get());

        $qids = $DB->get_records_sql("SELECT * FROM ".$CFG->prefix."knowledge_node_questions WHERE `knowledge_node`= $notion->knowledge_node_id");

        foreach($qids as $qid)
        {
            if($linked_resource->modulename == "scorm")
            {
                $sco = $DB->get_record('scorm_scoes', array('id' => $qid->question_id), '*');

                $render_q .= html_writer::tag('b',$linked_resource->display)." - ".$sco->title.html_writer::tag('hr', '');
            }
            else
            {
                $question = $DB->get_record('question', array('id' => $qid->question_id), '*');

                $render_q .= html_writer::tag('b',$question->name." : ").strip_tags($question->questiontext).html_writer::tag('hr', '');
            }
        }
        $accordion_inner = html_writer::tag('div', $render_q, array('class' => 'accordion-inner'));
        $accordion_collapse = html_writer::tag('div', $accordion_inner, array('class' => 'accordion-body collapse', 'id' => 'collapse-'.$notion->id));
        $togglers = html_writer::link('#collapse-'.$notion->id, html_writer::start_span('mod_introbox').
                                                                html_writer::tag('i', '', array('class' => 'icon-chevron-down')).
                                                                " $title->name (".count($qids)." question".plural($qids).")".
                                                                html_writer::end_span(), array(
                                                                                            'class' => 'accordion-toggle',
                                                                                            'data-toggle' => 'collapse',
                                                                                            'data-parent' => '#accordion2'));
        $togglers .= html_writer::link($CFG->wwwroot.'/mod/domoscio/linkto.php?id='.$cm->id.'&notion='.$notion->knowledge_node_id,
                                        html_writer::tag('button', '2. '.get_string('choose_q', 'domoscio'), array('type' => 'button', 'class' => 'btn btn-link pull-right')));
        $accordion_heading = html_writer::tag('div', $togglers, array('class' => 'well well-small' , 'style' => 'margin-bottom:0px'));
        $accordion_group = html_writer::tag('div', $accordion_heading.$accordion_collapse, array('class' => 'accordion-group'));
        echo $accordion = html_writer::tag('div', $accordion_group, array('class' => 'accordion', 'id' => 'accordion'));
    }

    if(empty($notions))
    {
        echo html_writer::tag('blockquote', get_string('notions_empty', 'domoscio'), array('class' => 'muted'));
    }

}

// --- STUDENT VIEW ---

elseif (user_has_role_assignment($USER->id,5)) {

    // Vérifie si l'étudiant s'est déjà connecté au plugin Domoscio
    $check = $DB->get_record('userapi', array('user_id' => $USER->id), '*');

    if(empty($check))
    {
        // Sinon, le plugin demande à l'api de créer un nouvel étudiant
        echo html_writer::tag('div', html_writer::tag('h5', 'Bienvenue, '.$USER->firstname, array('class' => 'mod_introbox')), array('class' => 'block'));
        echo "C'est votre première visite sur le plugin Domoscio. Nous en avons profité pour créer votre profil d'apprentissage. Cliquez sur le bouton
        ci-dessous pour continuer :<br/>";
        echo html_writer::tag('button', "Lancez-vous !", array('type' => 'button','onclick'=>"javascript:location.href='$CFG->wwwroot/mod/domoscio/view.php?id=$cm->id'"));

        create_student();
    }
    else
    {
        $kn_student = manage_student($config, $domoscio, $check);

        $count = count_tests($config);
        $url2=new moodle_url("$CFG->wwwroot/mod/domoscio/index.php");

        echo html_writer::start_span('badge badge-important').html_writer::tag('h4', count($count)).html_writer::end_span().get_string('text2', 'domoscio').plural($count).get_string('text3', 'domoscio')." ";

        if(!empty($count)){echo $OUTPUT->action_link( $url2, "Faire les rappels");}

        $introbox = html_writer::tag('b', get_string('reviewed', 'domoscio'), array('class' => 'mod_introbox')).
                    html_writer::link($linked_resource->url, $linked_resource->display);
        echo html_writer::tag('div', $introbox, array('class' => 'block'));

        echo "<hr/>";

        if(!empty($check) && $kn_student)
        {
            $_SESSION['todo'] = $_SESSION['results'] = array();

            foreach($kn_student as $notion)
            {
                $item = json_decode($rest->setUrl($config, 'knowledge_nodes', $notion->knowledge_node_id)->get());
                $reminder = date('d/m/Y '.get_string('at', 'domoscio').' H:i',strtotime($notion->next_review_at));
                $_SESSION['todo'][] = $item->id;

                $accordion_inner = html_writer::tag('div', get_string('next_due', 'domoscio').$reminder, array('class' => 'accordion-inner'));
                $accordion_collapse = html_writer::tag('div', $accordion_inner, array('class' => 'accordion-body collapse', 'id' => 'collapse-'.$notion->id));
                $togglers = html_writer::link('#collapse-'.$notion->id, html_writer::start_span('mod_introbox').
                                                                      html_writer::tag('i', '', array('class' => 'icon-chevron-down')).
                                                                      $item->name.
                                                                      html_writer::end_span(), array(
                                                                                                  'class' => 'accordion-toggle',
                                                                                                  'data-toggle' => 'collapse',
                                                                                                  'data-parent' => '#accordion'));
                $accordion_heading = html_writer::tag('div', $togglers, array('class' => 'well well-small' , 'style' => 'margin-bottom:0px'));
                $accordion_group = html_writer::tag('div', $accordion_heading.$accordion_collapse, array('class' => 'accordion-group'));
                echo $accordion = html_writer::tag('div', $accordion_group, array('class' => 'accordion', 'id' => 'accordion'));
            }

        }

        echo html_writer::tag('button', get_string('do_test', 'domoscio'), array('type' => 'button',
                                                                               'onclick'=>"javascript:location.href='$CFG->wwwroot/mod/domoscio/doquiz.php?kn=".array_shift($_SESSION['todo'])."&t=".time()."'"));

    }

/*
    echo "<hr/>Statistiques :<br/>";
    echo "<canvas id='historyChart' width='800' height='400'></canvas>";

    $formatted = implode(',',str_split($kn_student->history));
    $attempts = array();
    foreach(str_split($kn_student->history) as $k=>$result)
    {
      $attempts[] = '"'.$k.'"';
    }
    $attempts = implode(',', $attempts);
?>
<script type="text/javascript" src="Chart.min.js"></script>
<script type="text/javascript">
var ctx = document.getElementById("historyChart").getContext("2d");

var data = {
    labels: [<?php print_r($attempts);?>],
    datasets: [
        {
            label: "Results",
            fillColor: "rgba(0,220,0,0.2)",
            strokeColor: "rgba(0,180,0,1)",
            pointColor: "rgba(0,220,0,1)",
            pointStrokeColor: "#fff",
            pointHighlightFill: "#fff",
            pointHighlightStroke: "rgba(220,220,220,1)",
            data: [<?php echo $formatted;?>]
        },
    ]
};
var options = {
  ///Boolean - Whether grid lines are shown across the chart
  scaleShowGridLines : true,
};
var historyChart = new Chart(ctx).Line(data, options);
</script>
<?php
*/
}
// Finish the page.
echo $OUTPUT->footer();
