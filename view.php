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

$config = get_config('domoscio');
$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('d', 0, PARAM_INT);  // ... domoscio instance ID - it should be named as the first character of the module.


if ($id) {
    $cm         = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $domoscio  = $DB->get_record('domoscio', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $domoscio  = $DB->get_record('domoscio', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $domoscio->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('domoscio', $domoscio->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);



/*$event = \mod_domoscio\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $domoscio);
$event->trigger();*/



// Print the page header.

$PAGE->set_url('/mod/domoscio/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($domoscio->name));
$PAGE->set_heading("Domoscio for Moodle");


//REDIRIGE DIRECTEMENT VERS l'index
//redirect("$CFG->wwwroot/mod/domoscio/index.php?id=".$COURSE->id);

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('domoscio-'.$somevar);
 */

// Output starts here.

echo $OUTPUT->header();

// Replace the following lines with you own code.
echo $OUTPUT->heading($domoscio->name);

// Conditions to show the intro can change to look for own settings or whatever.
if ($domoscio->intro) {
    echo $OUTPUT->box(format_module_intro('domoscio', $domoscio, $cm->id), 'generalbox mod_introbox', 'domosciointro');
}

// --- VUE PROFESSEUR ---

if (user_has_role_assignment($USER->id,3)) {

    echo "Le plugin est lié à la ressource suivante :<br/>";
    $rest = new domoscio_client();

    $resource = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_nodes/$domoscio->resource_id?token=$config->domoscio_apikey")->get());

    echo get_resource_info($resource->id);
    echo "<BR/><HR/>Le plugin propose les questions suivantes :<br/>";

    $questions = $DB->get_records_sql("SELECT `question_id` FROM `mdl_knowledge_node_questions` WHERE `instance` = $domoscio->id");

    foreach($questions as $question){echo $question->question_id.", ";}

    echo "<hr/>Inscrivez les questions que vous souhaitez proposer aux étudiants.
    <br/>Sélectionnez l'un des quiz ci-dessous pour associer les questions pour l'ancrage :<hr/>";

    $quizzes = $DB->get_records_sql("SELECT `id`, `name` FROM `mdl_quiz` WHERE `course` = ".$course->id);

    foreach($quizzes as $quiz)
    {
        $url = new moodle_url("$CFG->wwwroot/mod/domoscio/linkto.php?id=".$cm->id."&q=".$quiz->id);
        echo $OUTPUT->action_link( $url, $quiz->name )."<br/>";
    }


    //require_once($CFG->dirroot.'/mod/domoscio/sdk/auth.php');

}

// --- VUE ETUDIANT ---

elseif (user_has_role_assignment($USER->id,5)) {

    // Vérifie si l'étudiant s'est déjà connecté au plugin Domoscio
    $check = $DB->get_record_sql("SELECT * FROM `mdl_userapi` WHERE `user_id` =".$USER->id);

    if(!empty($check))
    {
        // Si oui, le plugin récupère les données de l'étudiant
        echo "Utilisateur inscrit<br/>";

        $rest = new domoscio_client();

        $student = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/students/$check->uniq_id?token=$config->domoscio_apikey")->get());

        print_r($student);

        // Vérifie si un knowledge_node_student est créé
        $check_knstudent = $DB->get_record_sql("SELECT * FROM `mdl_knowledge_node_students` WHERE `user` = $USER->id AND `instance` = $domoscio->id");

        if(!empty($check_knstudent))
        {
            //Si oui, le plugin récupère les données du KN student
            $rest = new domoscio_client();

            $kn_student = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_node_students/$check_knstudent->kn_student_id?token=$config->domoscio_apikey")->get());

            print_r($kn_student);

        }
        else
        {
            $jsonkn = json_encode(array('knowledge_node_id' => intval($domoscio->resource_id), 'student_id' => intval($student->id)));

            print_r($jsonkn);

            $rest = new domoscio_client();

            $kn_student = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_node_students/?token=$config->domoscio_apikey")->post($jsonkn));

            print_r($kn_student);

            // Le plugin récupère le knowledge_node_student id créé par l'api et l'inscrit en DB
            $record = new stdClass();
            $record->user = $USER->id;
            $record->kn_student_id = $kn_student->id;
            $record->instance = $domoscio->id;
            $insert = $DB->insert_record('knowledge_node_students', $record, false);
        }
    }
    else
    {
        // Sinon, le plugin demande à l'api de créer un nouvel étudiant
        echo "Première visite<br/>";

        $json = json_encode(array(
            'student_group_id' => strval("0"),
            'civil_profile_attributes' => array(
                                                'name' => strval($USER->firstname." ".$USER->lastname),
                                                'sexe' => strval("male"),
                                                'day_of_birth' => strval("11-05-1989"),
                                                'place_of_birth' => strval("FR"),
                                                'country_of_residence' => strval($USER->country),
                                                'city_of_residence' => strval($USER->city)
                                                ),
            'learning_profile_attributes' => array(
                                                    'forgetting_parameters' => strval("[1,2,3]")
                                                    )
        ));

        $rest = new domoscio_client();

        $student = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/students/?token=$config->domoscio_apikey")->post($json));

        echo $student->id;

        // Le plugin récupère l'uniq_id créé par l'api et l'inscrit en DB
        $record = new stdClass();
        $record->user_id = $USER->id;
        $record->uniq_id = $student->id;
        $insert = $DB->insert_record('userapi', $record, false);

    }
/*
    echo "<br/>Voici les données dont l'API aura besoin lors de la première connexion de l'étudiant<hr/>";
    // Récupère les identifiants des dernières tentatives de réponse aux quiz
    $sqllast = "SELECT `quiz`, MAX(`uniqueid`) AS `uniqueid`
                FROM `mdl_quiz_attempts`
                WHERE `userid` = $USER->id
                AND `state` = 'finished'
                GROUP BY `quiz`
                ORDER BY `uniqueid` ASC";

    $last_attempts = $DB->get_records_sql($sqllast);
    $data = array();
    foreach($last_attempts as $last_attempt)
    {
        $data[] = $last_attempt->uniqueid;
    }

    $datas = implode(',', $data);

    //Récupère les questions où l'étudiant à correctement répondu lors de ses dernières tentatives
    $sqlright = "SELECT `questionid`
                FROM `mdl_question_attempts`
                WHERE `mdl_question_attempts`.`rightanswer` = `mdl_question_attempts`.`responsesummary`
                AND `questionusageid` IN ($datas)
                ORDER BY `questionid` ASC";

    $rightresponses = $DB->get_records_sql($sqlright);

    //Récupère les questions où l'étudiant à mal répondu lors de ses dernières tentatives
    $sqlwrong = "SELECT `questionid`
                FROM `mdl_question_attempts`
                WHERE `mdl_question_attempts`.`rightanswer` != `mdl_question_attempts`.`responsesummary`
                AND `questionusageid` IN ($datas)
                ORDER BY `questionid` ASC";

    $wrongresponses = $DB->get_records_sql($sqlwrong);

    echo "Lors de ses dernières tentatives, l'étudiant ".$USER->id." a correctement répondu aux questions suivantes :<br/>";
    foreach ($rightresponses as $rightresponse){echo $rightresponse->questionid.", ";}
    echo "<hr/>";
    echo "Lors de ses dernières tentatives, l'étudiant ".$USER->id." a mal répondu aux questions suivantes :<br/>";
    foreach ($wrongresponses as $wrongresponse){echo $wrongresponse->questionid.", ";}
    echo "<hr/>";

    //-----------------------
*/
    echo "<br/>";
    $url1=new moodle_url("$CFG->wwwroot/mod/domoscio/doquiz.php?id=$cm->id");
    echo $OUTPUT->action_link( $url1, "Faire les tests");
}

// Finish the page.
echo $OUTPUT->footer();
