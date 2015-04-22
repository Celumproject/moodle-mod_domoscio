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
 * Library of interface functions and constants for module domoscio
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the domoscio specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_domoscio
 * @copyright  2015 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/sdk/client.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Example constant, you probably want to remove this :-)
 */
define('DOMOSCIO_ULTIMATE_ANSWER', 42);

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function domoscio_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the domoscio into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $domoscio Submitted data from the form in mod_form.php
 * @param mod_domoscio_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted domoscio record
 */

function domoscio_add_instance(stdClass $domoscio, mod_domoscio_mod_form $mform = null) {
    global $DB, $COURSE;

    $config = get_config('domoscio');

    $domoscio->timecreated = time();

    // Si le cours n'est pas référencé en tant que knowledge_graph, en créé un nouveau sur l'api
    $check = $DB->get_records_sql("SELECT `knowledge_graph_id` FROM `mdl_knowledge_graphs` WHERE `course_id` =".$domoscio->course);

    $rest = new domoscio_client();

    if(count($check) > 0) // Récupère le knowledge_graph_id existant
    {
        foreach($check as $result)
        {
            $graphid = $result->knowledge_graph_id;
        }

        $graph = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_graphs/$graphid?token=$config->domoscio_apikey")->get());
    }
    else // Sinon récupère un nouveau knowledge_graph_id et l'inscrit en DB
    {
        $json = json_encode(array('name' => strval($COURSE->fullname)));

        $graph = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_graphs/?token=$config->domoscio_apikey")->post($json));

        $graphid = $graph->id;
        $knowledge_graph = new stdClass;
        $knowledge_graph->course_id = $domoscio->course;
        $knowledge_graph->knowledge_graph_id = $graph->id;

        $knowledge_graph = $DB->insert_record('knowledge_graphs', $knowledge_graph);
    }

    // Si la ressource n'est pas référencé en tant que knowledge_node, en créé un nouveau sur l'api
    $check = $DB->get_records_sql("SELECT `knowledge_node_id` FROM `mdl_knowledge_nodes` WHERE `resource_id` =".$domoscio->resource);

    $rest = new domoscio_client();

    if(count($check) > 0) // Récupère le knowledge_node_id existant
    {
        foreach($check as $result)
        {
            $id = $result->knowledge_node_id;
        }

        $resource = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_nodes/$id?token=$config->domoscio_apikey")->get());
    }
    else // Sinon récupère un nouveau knowledge_node et l'inscrit en DB
    {
        $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                                    'name' => strval($domoscio->resource)));

        $resource = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_nodes/?token=$config->domoscio_apikey")->post($json));

        $knowledge_node = new stdClass;
        $knowledge_node->resource_id = $domoscio->resource;
        $knowledge_node->knowledge_node_id = $resource->id;

        $knowledge_node = $DB->insert_record('knowledge_nodes', $knowledge_node);
    }

    // Le plugin récupère le resource_id créé par l'api et l'inscrit en DB avec la nouvelle instance de plugin domoscio

    $domoscio->resource_id = $resource->id;

    $domoscio->id = $DB->insert_record('domoscio', $domoscio);

    domoscio_grade_item_update($domoscio);

    return $domoscio->id;

}

/**
 * Updates an instance of the domoscio in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $domoscio An object from the form in mod_form.php
 * @param mod_domoscio_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function domoscio_update_instance(stdClass $domoscio, mod_domoscio_mod_form $mform = null) {
    global $DB;

    $domoscio->timemodified = time();
    $domoscio->id = $domoscio->instance;

    // You may have to add extra stuff in here.

    $result = $DB->update_record('domoscio', $domoscio);

    domoscio_grade_item_update($domoscio);

    return $result;
}

/**
 * Removes an instance of the domoscio from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function domoscio_delete_instance($id) {
    global $DB;

    if (! $domoscio = $DB->get_record('domoscio', array('id' => $id))) {
        return false;
    }

    // Delete any dependent records here.

    $DB->delete_records('domoscio', array('id' => $domoscio->id));

    domoscio_grade_item_delete($domoscio);

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $domoscio The domoscio instance record
 * @return stdClass|null
 */
function domoscio_user_outline($course, $user, $mod, $domoscio) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $domoscio the module instance record
 */
function domoscio_user_complete($course, $user, $mod, $domoscio) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in domoscio activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function domoscio_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link domoscio_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function domoscio_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link domoscio_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function domoscio_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function domoscio_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function domoscio_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of domoscio?
 *
 * This function returns if a scale is being used by one domoscio
 * if it has support for grading and scales.
 *
 * @param int $domoscioid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given domoscio instance
 */
function domoscio_scale_used($domoscioid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('domoscio', array('id' => $domoscioid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of domoscio.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any domoscio instance
 */
function domoscio_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('domoscio', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given domoscio instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $domoscio instance object with extra cmidnumber and modname property
 * @param bool $reset reset grades in the gradebook
 * @return void
 */
function domoscio_grade_item_update(stdClass $domoscio, $reset=false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($domoscio->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($domoscio->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $domoscio->grade;
        $item['grademin']  = 0;
    } else if ($domoscio->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$domoscio->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($reset) {
        $item['reset'] = true;
    }

    grade_update('mod/domoscio', $domoscio->course, 'mod', 'domoscio',
            $domoscio->id, 0, null, $item);
}

/**
 * Delete grade item for given domoscio instance
 *
 * @param stdClass $domoscio instance object
 * @return grade_item
 */
function domoscio_grade_item_delete($domoscio) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/domoscio', $domoscio->course, 'mod', 'domoscio',
            $domoscio->id, 0, null, array('deleted' => 1));
}

/**
 * Update domoscio grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $domoscio instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function domoscio_update_grades(stdClass $domoscio, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();

    grade_update('mod/domoscio', $domoscio->course, 'mod', 'domoscio', $domoscio->id, 0, $grades);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function domoscio_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for domoscio file areas
 *
 * @package mod_domoscio
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function domoscio_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the domoscio file areas
 *
 * @package mod_domoscio
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the domoscio's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function domoscio_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding domoscio nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the domoscio module instance
 * @param stdClass $course current course record
 * @param stdClass $module current domoscio instance record
 * @param cm_info $cm course module information
 */
function domoscio_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // TODO Delete this function and its docblock, or implement it.
}

/**
 * Extends the settings navigation with the domoscio settings
 *
 * This function is called when the context for the page is a domoscio module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $domoscionode domoscio administration node
 */
function domoscio_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $domoscionode=null) {
    // TODO Delete this function and its docblock, or implement it.
}

function is_user_with_role($courseid, $rolename, $userid = 0) {
    $result = false;
    $roles = get_user_roles(context_course::instance($courseid), $userid, false);
    foreach ($roles as $role) {
        if ($role->shortname == $rolename) {
            $result = true;
            break;
        }
    }
    return $result;
}

function get_resource_info($knowledge_node) {

    global $DB, $CFG;

    $query = "SELECT `mdl_course_modules`.`module`,`mdl_course_modules`.`instance`
            FROM `mdl_course_modules` INNER JOIN `mdl_knowledge_nodes`
            ON `mdl_course_modules`.`id` = `mdl_knowledge_nodes`.`resource_id`
            WHERE `mdl_knowledge_nodes`.`knowledge_node_id` =".$knowledge_node;

    $resource = $DB->get_record_sql($query);

    $modulename = null;

    switch($resource->module) {
        case 3:
            $modulename = "mdl_book";
            break;

        case 14:
            $modulename = "mld_lesson";
            break;

        case 16:
            $modulename = "mdl_page";
            break;

        case 18:
            $modulename = "mdl_resource";
            break;
    }

    $query = "SELECT name FROM $modulename WHERE id = $resource->instance";

    $moduleinfo = $DB->get_record_sql($query);

    return $moduleinfo->name;
}
/* Compte le nombre de rappels */
function count_tests($config)
{
    global $DB, $USER, $CFG;

    $kn_students = $DB->get_records_sql("SELECT kn_student_id FROM mdl_knowledge_node_students WHERE user = $USER->id");
    $i = 0;
    $list = array();
    foreach($kn_students as $kn_student)
    {
        $rest = new domoscio_client();
        $date = json_decode($rest->setUrl("http://stats-engine.domoscio.com/v1/companies/$config->domoscio_id/knowledge_node_students/$kn_student->kn_student_id?token=$config->domoscio_apikey")->get());

        if(strtotime($date->next_review_at) < time())
        {
            $list[] = $kn_student->kn_student_id;
        }
    }

    return $list;
}

/* Affiche l'interface de quiz */
function display_questions($question)
{
    echo "<div class='que ".$question->qtype." deferredfeedback notyetanswered'>";
        echo "<div class='info'>";
            echo "<h3 class='no'>Question <span class='qno'>".$question->id."</span></h3>";
        echo "</div>";
        echo "<div class='content'>";
            echo "<div class='formulation'>";
                if($question->qtype == "calculated" || $question->qtype == "numerical" || $question->qtype == "shortanswer")
                {
                    echo get_inputanswer($question);
                }
                elseif($question->qtype == "multichoice" || $question->qtype == "calculatedmulti" || $question->qtype == "truefalse")
                {
                    echo get_multichoiceanswer($question);
                }
                elseif($question->qtype == "multianswer")
                {
                    echo get_multianswer($question);
                }
                elseif($question->qtype == "match")
                {
                    echo get_match($question);
                }
            echo "</div>";
        echo "</div>";
    echo "</div>";
}

/* Chope les réponses */
function get_answers($qnum)
{
    global $CFG, $DB;
    $sqlanswers = "SELECT *
                    FROM `mdl_question_answers`
                    WHERE `question` = $qnum";

    $answers = $DB->get_records_sql($sqlanswers);
    return $answers;
}

/* Chope les bonnes réponses */
function get_right_answers($qnum)
{
    global $CFG, $DB;
    $sqlanswers = "SELECT *
                    FROM `mdl_question_answers`
                    WHERE `question` = $qnum
                    AND `fraction` = 1";

    $answers = $DB->get_record_sql($sqlanswers);
    return $answers;
}

//Génère l'affichage des questions qui attendent un champ de saisie
function get_inputanswer($question)
{
    $display =
    "<div class'qtext'>
        <p>".$question->questiontext."</p>
    </div>
    <div class='ablock'>
        <label for = 'q0:".$question->id."_answer'>Answer :</label>
        <span class='answer'>
            <input id='q0:".$question->id."_answer' type='text' size='80' name='q0:".$question->id."_answer'></input>
        </span>
    </div>";

    return $display;
}

//Génère l'affichage des questions QCM
function get_multichoiceanswer($question)
{
    $i = 0;

    $radio = array();

    $answers_list = get_answers($question->id);

    foreach($answers_list as $answer)
    {
        $radio[] =
            "<div class='r0'>
                <input id='q0:".$question->id."_answer' type='radio' value='".$i."' name='q0:".$question->id."_answer'></input>
                <label for = 'q0:".$question->id."_answer'>".strip_tags($answer->answer)."</label>
            </div>";
        $i++;
    }
    $radio_fetched = implode("", $radio);

    $display =
    "<div class'qtext'>
        <p>".$question->questiontext."</p>
    </div>
    <div class='ablock'>
        <div class='prompt'>Select one :</div>
        <div class='answer'>".$radio_fetched.
        "</div>
    </div>";

    return $display;
}

/* Génère l'affichage d'une question de type texte à trous*/
function get_multianswer($question)
{
    global $CFG, $DB;

    // Récupère les sous-questions d'un texte à trous
    $sqlanswers = "SELECT `questiontext`, `qtype`
                    FROM `mdl_question`
                    WHERE `parent` =".$question->id;

    $answers = $DB->get_records_sql($sqlanswers);

    // Parse les réponses aux sous-questions et récupère leur contenu
    $result = array();

    $i = 1;

    foreach($answers as $answer)
    {
        preg_match("/%100%(.*?)#/", $answer->questiontext, $matchesOK);
        preg_match("/E:(.*?)#W/", $answer->questiontext, $matchesWrong);
        $result[$answer->qtype.$i][] = $matchesOK[1];
        $result[$answer->qtype.$i][] = $matchesWrong[1];
        $i++;
    }

    // Retrouve le texte de la question
    $sql_qtext = "SELECT `questiontext`
                FROM `mdl_question`
                WHERE `id` =".$question->id;

    $qtext = $DB->get_record_sql($sql_qtext);

    // Parse the text for each holes and replace by HTML select
    preg_match_all("/({#.*?})/", $qtext->questiontext, $patterns);
    $i = 0;
    foreach($patterns[0] as $pattern)
    {
        $patterns[0][$i] = "'".$patterns[0][$i]."'";
        $i++;
    }

    $replacements = array();
    $i = $j = 1;

    foreach($result as $key => $hole)
    {
        if($key == "multichoice".$j)
        {
            $options = array();

            foreach($hole as $k => $option)
            {
                $options[] = "<option value='".$option."'>".$option."</option>";
            }

            shuffle($options);
            array_unshift($options , "<option value=''></option>");

            $options_fetched = implode("", $options);

            $replacements[] =
                "<span class='subquestion'>
                    <label class='subq accesshide' for='q0:".$question->id."_sub".$i."_answer'>
                    Answer
                    </label>
                    <select id='q0:".$question->id."_sub".$i."_answer' class='select menuq0:".$question->id."_sub".$i."_answer' name='q0:".$question->id."_sub".$i."_answer'>".$options_fetched.
                    "</select>
                </span>";
        }
        elseif($key == "shortanswer".$j)
        {
            $replacements[] =
            "<span class='subquestion'>
                <label class='subq accesshide' for='q0:".$question->id."_sub".$i." answer'>
                Answer
                </label>
                <input id=id='q0:".$question->id."_sub".$i."_answer' type='text' size='7' name='q0:".$question->id."_sub".$i."_answer'></input>
            </span>";
        }
        $i++;
        $j++;
    }

    return preg_replace($patterns[0], $replacements, $qtext->questiontext);
}

// Génère l'affichage des questions de type Match (drag and drop)
function get_match($question)
{
    global $CFG, $DB;
    $sqlquestions = "SELECT *
                    FROM `mdl_qtype_match_subquestions`
                    WHERE `questionid` =".$question->id;

    $subquestions = $DB->get_records_sql($sqlquestions);

    $options = array();

    foreach($subquestions as $k => $subquestion)
    {
        $options[] = "<option value='".$subquestion->answertext."'>".$subquestion->answertext."</option>";
    }

    shuffle($options);
    array_unshift($options , "<option value=''>Choose...</option>");

    $options_fetched = implode("", $options);

    $table = array();
    $i = 1;

    foreach($subquestions as $subquestion)
    {
        $table[] =
        "<tr class='r0'>
            <td class='text'>
                <p>".$subquestion->questiontext."</p>
            </td>
            <td class='control'>
                <label class='subq accesshide' for='q0:".$question->id."_sub".$i." answer'>
                Answer
                </label>
                <select id='menuq0:".$question->id."_sub".$i."' class='select menuq0:".$question->id."_sub".$i."' name='q0:".$question->id."_sub".$i."'>".$options_fetched.
                "</select>
            </td>
        </tr>";

        $i++;
    }

    $table_fetched = implode("", $table);

    $display =
    "<div class='qtext'>
        <p>".$question->questiontext."</p>
    </div>
    <table class='answer'>
        <tbody>"
            .$table_fetched.
        "</tbody>
    </table>";

    return $display;
}

/*----------------- RESULTATS ----------------*/

//Génère l'affichage des résultats aux questions de saisie
function get_inputresult($question, $post)
{
    $answer = get_right_answers($question->id);

    if($post['q0:'.$question->id.'_answer'] != $answer->answer)
    {

    echo"<div class'qtext'>
            <p>".$question->questiontext."</p>
        </div>
        <div class='ablock'>
            <label for = 'q0:".$question->id."_answer'>Answer :</label>
            <span class='answer'>
                <input class='incorrect' id='q0:".$question->id."_answer' type='text' value='".$post['q0:'.$question->id.'_answer']."' readonly='readonly' size='80' name='q0:".$question->id."_answer'></input>
            </span>
        </div>
        <div class='outcome'>
            <div class='rightanswer'>The right answer is : ".$answer->answer.
            "</div>
        </div>";
        $result = 0;
    }
    else
    {

    echo"<div class'qtext'>
            <p>".$question->questiontext."</p>
        </div>
        <div class='ablock'>
            <label for = 'q0:".$question->id."_answer'>Answer :</label>
            <span class='answer'>
                <input class='correct' id='q0:".$question->id."_answer' type='text' value='".$post['q0:'.$question->id.'_answer']."' readonly='readonly' size='80' name='q0:".$question->id."_answer'></input>
            </span>
        </div>
        <div class='outcome'>
            <div class='rightanswer'>The right answer is : ".$answer->answer.
            "</div>
        </div>";
        $result = 100;
    }
    return $result;
}

//Génère l'affichage des résultats aux questions QCM
function get_multichoiceresult($question, $post)
{
    $i = 0;

    $radio = array();

    $answers_list = get_answers($question->id);

    $rightanswer = get_right_answers($question->id);

    foreach($answers_list as $answer)
    {
        $class = null;

        if($i == $post['q0:'.$question->id.'_answer'])
        {
            $checkradio = "checked='checked'";
            if($answer->answer !== $rightanswer->answer)
            {
                $class = "incorrect";
                $result = 0;
            }
            else
            {
                $class = "correct";
                $result = 100;
            }
        }
        else
        {
            $checkradio = null;
        }

        $radio[] =
            "<div class='r0 ".$class."''>
                <input disabled='disabled' id='q0:".$question->id."_answer' type='radio' value='".$i."' name='q0:".$question->id."_answer'".$checkradio."></input>
                <label for = 'q0:".$question->id."_answer'>".strip_tags($answer->answer)."</label>
            </div>";
        $i++;
    }
    $radio_fetched = implode("", $radio);

    echo
    "<div class'qtext'>
        <p>".$question->questiontext."</p>
    </div>
    <div class='ablock'>
        <div class='prompt'>Select one :</div>
        <div class='answer'>".$radio_fetched.
        "</div>
    </div>
    <div class='outcome'>
        <div class='rightanswer'>The right answer is : ".$rightanswer->answer.
        "</div>
    </div>";

    return $result;
}

/* Génère l'affichage des résultats aux questions de type texte à trous*/
function get_multiresult($question, $post)
{
    global $CFG, $DB;

    // Récupère les sous-questions d'un texte à trous
    $sqlanswers = "SELECT `id`, `qtype`, `questiontext`
                    FROM `mdl_question`
                    WHERE `parent` =".$question->id;

    $answers = $DB->get_records_sql($sqlanswers);

    $i = 1;
    $result = array();
    foreach($answers as $answer)
    {
        preg_match("/%100%(.*?)#/", $answer->questiontext, $matchesOK);
        $result[$answer->qtype.$i][] = $matchesOK[1];
        $i++;
    }

    // Retrouve le texte de la question
    $sql_qtext = "SELECT `questiontext`
                FROM `mdl_question`
                WHERE `id` =".$question->id;

    $qtext = $DB->get_record_sql($sql_qtext);

    // Parse the text for each holes and replace by HTML select
    preg_match_all("/({#.*?})/", $qtext->questiontext, $patterns);
    $i = 0;
    foreach($patterns[0] as $pattern)
    {
        $patterns[0][$i] = "'".$patterns[0][$i]."'";
        $i++;
    }

    $replacements = array();
    $i = $j = 1;
    $subresult = 0;
    foreach($result as $key => $hole)
    {
        if(($post['q0:'.$question->id.'_sub'.$i.'_answer']) == $hole[0])
        {
            $class = "correct";
            $subresult += 100;
        }
        else
        {
            $class = "incorrect";
            $subresult += 0;
        }

        if($key == "multichoice".$j)
        {
            $replacements[] =
                "<span class='subquestion'>
                    <label class='subq accesshide' for='q0:".$question->id."_sub".$i."_answer'>
                    Answer
                    </label>
                    <select id='q0:".$question->id."_sub".$i."_answer' class='select menuq0:".$question->id."_sub".$i."_answer ".$class."' name='q0:".$question->id."_sub".$i."_answer' disabled='disabled'>
                        <option>".$post['q0:'.$question->id.'_sub'.$i.'_answer']."</option>
                    </select>
                </span>";
        }
        elseif($key == "shortanswer".$j)
        {
            $replacements[] =
            "<span class='subquestion'>
                <label class='subq accesshide' for='q0:".$question->id."_sub".$i." answer'>
                Answer
                </label>
                <input class='".$class."' id='q0:".$question->id."_sub".$i."_answer' type='text' size='7' name='q0:".$question->id."_sub".$i."_answer' value='".$post['q0:'.$question->id.'_sub'.$i.'_answer']."' readonly='readonly'></input>
            </span>";
        }
        $i++;
        $j++;


    }
    if(count($answers) != $subresult){$result = 0;}else{$result = 1;}

    $display = preg_replace($patterns[0], $replacements, $qtext->questiontext);

    echo $display;
    return $result;
}

// Génère l'affichage des réponses aux questions de type Match (drag and drop)
function get_matchresult($question, $post)
{
    global $CFG, $DB;
    $sqlquestions = "SELECT *
                    FROM `mdl_qtype_match_subquestions`
                    WHERE `questionid` =".$question->id;

    $subquestions = $DB->get_records_sql($sqlquestions);

    $options = array();

    $table = array();
    $i = 1;
    $subresult = 0;

    foreach($subquestions as $subquestion)
    {
        $class = null;
        if(($post['q0:'.$question->id.'_sub'.$i]) == $subquestion->answertext)
        {
            $class = "correct";
            $subresult += 100;
        }
        else
        {
            $class = "incorrect";
            $subresult += 0;
        }

        $table[] =
        "<tr class='r0'>
            <td class='text'>
                <p>".$subquestion->questiontext."</p>
            </td>
            <td class='control'>
                <label class='subq accesshide' for='q0:".$question->id."_sub".$i." answer'>
                Answer
                </label>
                <select id='q0:".$question->id."_answer' class='select menu q0:".$question->id."_sub".$i." answer ".$class."' name='q0:".$question->id."_answer' disabled='disabled'>
                    <option>".$post['q0:'.$question->id.'_sub'.$i]."</option>
                </select>
            </td>
        </tr>";
        $i++;
    }

    $table_fetched = implode("", $table);

    echo
    "<div class='qtext'>
        <p>".$question->questiontext."</p>
    </div>
    <table class='answer'>
        <tbody>"
            .$table_fetched.
        "</tbody>
    </table>";

    if(count($subquestions) != $subresult){$result = 0;}else{$result = 1;}

    return $result;
}
