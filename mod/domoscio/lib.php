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
 * @copyright  2015 Domoscio SA
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
    global $DB, $COURSE, $CFG;

    $config = get_config('domoscio');

    $domoscio->timecreated = time();

    // Si le cours n'est pas référencé en tant que knowledge_graph, en créé un nouveau sur l'api
    $check = $DB->get_records('knowledge_graphs', array('course_id' => $domoscio->course), '', 'knowledge_graph_id');

    $rest = new domoscio_client();

    if(count($check) > 0) // Récupère le knowledge_graph_id existant
    {
        foreach($check as $result)
        {
          $graphid = $result->knowledge_graph_id;
        }

        $graph = json_decode($rest->setUrl($config, 'knowledge_graphs', $graphid)->get());
    }
    else // Sinon récupère un nouveau knowledge_graph_id et l'inscrit en DB
    {
        $json = json_encode(array('name' => strval($COURSE->fullname)));

        $graph = json_decode($rest->setUrl($config, 'knowledge_graphs', null)->post($json));

        $graphid = $graph->id;
        $knowledge_graph = new stdClass;
        $knowledge_graph->course_id = $domoscio->course;
        $knowledge_graph->knowledge_graph_id = $graph->id;

        $knowledge_graph = $DB->insert_record('knowledge_graphs', $knowledge_graph);
    }

    // Si la ressource n'est pas référencé en tant que knowledge_node, en créé un nouveau sur l'api
    $check = $DB->get_records('knowledge_nodes', array('resource_id' => $domoscio->resource), '', 'knowledge_node_id');

    $rest = new domoscio_client();

    if(count($check) > 0) // Récupère le knowledge_node_id existant
    {
        foreach($check as $result)
        {
          $id = $result->knowledge_node_id;
        }

        $resource = json_decode($rest->setUrl($config, 'knowledge_nodes', $id)->get());
    }
    else // Sinon créé un nouveau knowledge_node et l'inscrit en DB
    {
        $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                                  'name' => strval($domoscio->resource)));

        $resource = json_decode($rest->setUrl($config, 'knowledge_nodes', null)->post($json));

        $knowledge_node = new stdClass;

        $knowledge_node->resource_id = $domoscio->resource;
        $knowledge_node->knowledge_node_id = $resource->id;
        $knowledge_node->instance = null;
        $knowledge_node->id = $DB->insert_record('knowledge_nodes', $knowledge_node);
    }

    // Le plugin récupère le resource_id créé par l'api et l'inscrit en DB avec la nouvelle instance de plugin domoscio

    $domoscio->resource_id = $resource->id;

    $linked_resource = get_resource_info($resource->id);
    $domoscio->resource_type = $linked_resource->modulename;

    $domoscio->id = $DB->insert_record('domoscio', $domoscio);

    if(isset($knowledge_node))
    {
        $knupd = new stdClass;
        $knupd->id = $knowledge_node->id;
        $knupd->instance = $domoscio->id;
        $addinstance = $DB->update_record('knowledge_nodes', $knupd, $bulk= false);
    }
    //domoscio_grade_item_update($domoscio);


    // Si la ressource à ancrer est un package SCORM, associe un nouveau knowledge node pour chaque SCO contenu dans le package
    if($linked_resource->modulename == "scorm" && count($check) == 0)
    {
        $scoes = get_scorm_scoes($resource->id);

        foreach($scoes as $sco)
        {
            // Créé un nouveau knowledge node
            $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                                      'name' => strval($sco->title)));

            $kn_sco = json_decode($rest->setUrl($config, 'knowledge_nodes', null)->post($json));

            // Ajoute les knowledge edges
            $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                                      'source_node_id' => strval($resource->id),
                                      'destination_node_id' => strval($kn_sco->id)));

            $knedge = json_decode($rest->setUrl($config, 'knowledge_edges', null)->post($json));


            // Inscrit le knowledge node du SCO en DB
            $knowledge_node_sco = new stdClass;
            $knowledge_node_sco->instance = $domoscio->id;
            $knowledge_node_sco->knowledge_node_id = $kn_sco->id;
            $knowledge_node_sco->resource_id = $domoscio->resource;
            $knowledge_node_sco->child_id = $sco->id;

            $knowledge_node_sco = $DB->insert_record('knowledge_nodes', $knowledge_node_sco);
        }

        //$import = write_scorm_content($domoscio->id, $linked_resource->cm);
    }

    if($linked_resource->modulename == "book" && count($check) == 0)
    {
        $chapters = get_book_chapters($resource->id);

        foreach($chapters as $chapter)
        {
            // Créé un nouveau knowledge node
            $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                                        'name' => strval($chapter->title)));

            $kn_chapter = json_decode($rest->setUrl($config, 'knowledge_nodes', null)->post($json));

            // Ajoute les knowledge edges
            $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                                        'source_node_id' => strval($resource->id),
                                        'destination_node_id' => strval($kn_chapter->id)));

            $knedge = json_decode($rest->setUrl($config, 'knowledge_edges', null)->post($json));


            // Inscrit le knowledge node du SCO en DB
            $knowledge_node_chapter = new stdClass;
            $knowledge_node_chapter->instance = $domoscio->id;
            $knowledge_node_chapter->knowledge_node_id = $kn_chapter->id;
            $knowledge_node_chapter->resource_id = $domoscio->resource;
            $knowledge_node_chapter->child_id = $chapter->id;

            $knowledge_node_chapter = $DB->insert_record('knowledge_nodes', $knowledge_node_chapter);
        }
    }


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

    $DB->delete_records('knowledge_node_students', array('instance' => $domoscio->id));
    $DB->delete_records('knowledge_node_questions', array('instance' => $domoscio->id));
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

function create_student() {
    global $USER;

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

    $student = json_decode($rest->setUrl($config, 'students', null)->post($json));

    echo $student->id;

    // Le plugin récupère l'uniq_id créé par l'api et l'inscrit en DB
    $record = new stdClass();
    $record->user_id = $USER->id;
    $record->uniq_id = $student->id;
    $insert = $DB->insert_record('userapi', $record, false);
}

/*  Assuming the student already logged in the Domoscio plugin, this function retrieves his knowledge_node_students
    or create a new knowledge_node_students if student never reviewed the plugin
    or retrieves his knowledge_node_students if another Domoscio instance is linked to the same resource
*/
function manage_student($config, $domoscio, $check) {
    global $USER, $CFG, $DB;

    $rest = new domoscio_client();

    // Retrive student data from API
    $student = json_decode($rest->setUrl($config, 'students', $check->uniq_id)->get());

    // Retrive all active knowledge nodes relative to this instance of Domoscio
    $knowledge_nodes = $DB->get_records_select('knowledge_nodes', "instance = $domoscio->id AND active <> 0");

    $kn_student = array();

    // Check if kn student exist for each knowledge_node, retrive data if so or create new one if not set
    foreach($knowledge_nodes as $kn)
    {
        if(!$kns_query = $DB->get_record('knowledge_node_students', array('knowledge_node_id' => $kn->knowledge_node_id, 'user' => $USER->id)))
        {
            $jsonkn = json_encode(array('knowledge_node_id' => intval($kn->knowledge_node_id), 'student_id' => intval($student->id)));

            $kndata = json_decode($rest->setUrl($config, 'knowledge_node_students', null)->post($jsonkn));

            // Get knowledge_node_student created and store it into database
            $record = new stdClass();
            $record->user = $USER->id;
            $record->instance = $domoscio->id;
            $record->knowledge_node_id = $kn->knowledge_node_id;
            $record->kn_student_id = $kndata->id;
            $insert = $DB->insert_record('knowledge_node_students', $record, false);
            $kns_query = $DB->get_record('knowledge_node_students', array('knowledge_node_id' => $kn->knowledge_node_id, 'user' => $USER->id));
        }

        $kn_student[] = json_decode($rest->setUrl($config, 'knowledge_node_students', $kns_query->kn_student_id)->get());

        // Check if API has results for this kn student, if not, search for results on Moddle DB, or invite to pass a first test
        $last_kn = end($kn_student);

        if($last_kn->history == "")
        {
            if($domoscio->resource_type == "scorm")
            {
                $score = $DB->get_record('scorm_scoes_track', array('scormid' => $scorm,
                                                                     'userid' => $USER->id,
                                                                      'scoid' => $_SESSION['scoid'],
                                                                    'element' => "cmi.score.scaled"));
            }
            else
            {
                $questions = $DB->get_records('knowledge_node_questions', array('instance' => $domoscio->id, 'knowledge_node' => $kn->knowledge_node_id), '', 'question_id');
                $list = array();

                foreach($questions as $question)
                {
                    $list[] = $question->question_id;
                }

                $list = join(',', $list);

                $scoredata = $DB->get_records_sql("SELECT AVG(".$CFG->prefix."question_attempt_steps.`fraction`) AS score
                                                     FROM ".$CFG->prefix."question_attempt_steps
                                               INNER JOIN ".$CFG->prefix."question_attempts
                                                       ON ".$CFG->prefix."question_attempts.`id` = ".$CFG->prefix."question_attempt_steps.`questionattemptid`
                                                    WHERE ".$CFG->prefix."question_attempt_steps.`userid` = $USER->id
                                                      AND ".$CFG->prefix."question_attempt_steps.`sequencenumber` = 2
                                                      AND ".$CFG->prefix."question_attempts.`questionid` IN ($list)
                                                      AND ".$CFG->prefix."question_attempts.`timemodified` =
                                                          (SELECT MAX(`timemodified`)
                                                           FROM ".$CFG->prefix."question_attempts)");

                $score = (round(array_shift($scoredata)->score))*100;
            }

            $json = json_encode(array('knowledge_node_student_id' => intval($last_kn->id),
                                                          'value' => intval($score)));

            $sending = $rest->setUrl($config, 'results', null)->post($json);

            array_pop($kn_student);

            $kn_student[] = json_decode($rest->setUrl($config, 'knowledge_node_students', $last_kn->id)->get());
        }

    }

    return $kn_student;
}

/*  Retrives course modules data and retrun display and useful datas*/
function get_resource_info($knowledge_node) {

    global $DB, $CFG, $OUTPUT;

    $query = "SELECT ".$CFG->prefix."course_modules.`module`,".$CFG->prefix."course_modules.`instance`, ".$CFG->prefix."course_modules.`id`
                FROM ".$CFG->prefix."course_modules
          INNER JOIN ".$CFG->prefix."knowledge_nodes
                  ON ".$CFG->prefix."course_modules.`id` = ".$CFG->prefix."knowledge_nodes.`resource_id`
               WHERE ".$CFG->prefix."knowledge_nodes.`knowledge_node_id` =".$knowledge_node;

    $resource = $DB->get_record_sql($query);

    $modulename = null;

    switch($resource->module) {
        case 3:
            $modulename = "book";
            break;

        case 14:
            $modulename = "lesson";
            break;

        case 16:
            $modulename = "page";
            break;

        case 18:
            $modulename = "scorm";
            break;
    }

    $moduleinfo = $DB->get_record($modulename, array('id' => $resource->instance), 'name');

    $return = new stdClass();

    $return->display = html_writer::img($OUTPUT->pix_url('icon',$modulename,$modulename,array('class'=>'icon')), '', array('class' => 'activityicon')) . " <span>$moduleinfo->name</span>";
    $return->modulename = $modulename;
    $return->instance = $resource->instance;
    $return->url = "$CFG->wwwroot/mod/$modulename/view.php?id=$resource->id";
    $return->cm = $resource->id;

    if($modulename == "scorm")
    {
        $sco = $DB->get_record_sql("SELECT *
                                    FROM ".$CFG->prefix."scorm_scoes
                              INNER JOIN ".$CFG->prefix."knowledge_nodes
                                      ON ".$CFG->prefix."knowledge_nodes.`child_id` = ".$CFG->prefix."scorm_scoes.`id`
                                   WHERE ".$CFG->prefix."knowledge_nodes.`knowledge_node_id` = $knowledge_node");

        if(!empty($sco))
        {
            $return->sco = " / ".$sco->title;
            $return->scoid = $sco->child_id;
        }
        else
        {
            $return->sco = $return->scoid = " ";
        }
    }
    else
    {
        $return->sco = $return->scoid = " ";
    }

    return $return;

}

/* Récupère les scoes inclus dans un package SCORM */
function get_scorm_scoes($kn)
{
    global $DB, $CFG;

    $instance = get_resource_info($kn)->instance;

    $scoes = $DB->get_records_sql("SELECT *
                                   FROM ".$CFG->prefix."scorm_scoes
                                  WHERE `scorm` = $instance
                                    AND `scormtype` = 'sco'");

    return $scoes;
}

function get_book_chapters($cm)
{
    global $DB, $CFG;

    $instance = get_resource_info($cm)->instance;

    $chapters = $DB->get_records_sql("SELECT *
                                   FROM ".$CFG->prefix."book_chapters
                                  WHERE `bookid` = $instance");

    return $chapters;
}

/* Recherche le fichier de structure contenu dans un package SCORM, retrouve les questions et les inscrits en DB
$instance est l'instance du plugin domoscio, $cm est le module de cours rattaché au plugin Domoscio */
function write_scorm_content($instance, $cm)
{
    global $DB, $CFG;

    $context = $DB->get_record_sql("SELECT *
                                    FROM ".$CFG->prefix."context WHERE `instanceid` = $cm
                                     AND `contextlevel` = '70'");

    $fileid = $DB->get_record_sql("SELECT `id`
                                   FROM ".$CFG->prefix."files
                                  WHERE `filename` = 'structure.xml'
                                    AND `contextid` = $context->id");

    $fs = get_file_storage();

    $file = $fs->get_file_by_id($fileid->id)->get_content();

    $content=simplexml_load_string($file) or die("Error: Cannot create object");

    $i = 0;
    foreach($content[$i]->celltest as $celltest)
    {
        // Création de l'entrée celltest
        $celltest_entry = new stdClass;
        $celltest_entry->name = (string)$celltest->title;
        $celltest_entry->qtype = (string)$celltest['type'];
        $celltest_entry->questiontext = (string)$celltest->questiontext;
        $celltest_entry->level = (string)$celltest->level;
        $celltest_entry->instance = $instance;

        $celltest_entry->id = $DB->insert_record('celltests', $celltest_entry);

        // Création des entrées proposition_lists
        foreach($celltest->proplist as $proplist)
        {
            $proplist_obj = new stdClass;
            $proplist_obj->cell_question_id = $celltest_entry->id;
            $proplist_obj->cell_question_type = (string)$proplist['type'];

            $proplist_obj->id = $DB->insert_record('proposition_lists', $proplist_obj);

            // Création des entrées propositions
            foreach($proplist->proposition as $prop)
            {
                $prop_obj = new stdClass;
                $prop_obj->proposition_list_id = $proplist_obj->id;
                $prop_obj->content = (string)$prop;
                if((string)$prop['right']==''){$prop_obj->right = "0";}else{$prop_obj->right = "1";}

                $prop_obj->id = $DB->insert_record('propositions', $prop_obj);
            }
        }
        $i++;
    }
}

/* This function counts all plugins where student is enrolled and due date has arrived */
function count_tests($config)
{
    global $DB, $USER, $CFG;

    //Check courses student is enrolled
    $course_enrol = $DB->get_records_sql("SELECT `courseid`
                                          FROM ".$CFG->prefix."enrol
                                    INNER JOIN ".$CFG->prefix."user_enrolments
                                            ON ".$CFG->prefix."user_enrolments.`enrolid` = ".$CFG->prefix."enrol.`id`
                                         WHERE ".$CFG->prefix."user_enrolments.`userid` = $USER->id");

    $courselist = array();
    foreach($course_enrol as $course)
    {
        $courselist[] = $course->courseid;
    }
    $list = array();

    //Retrives due date for these courses
    if(!empty($courselist))
    {
        $courselist = join(',', $courselist);
        $instances = $DB->get_records_sql("SELECT id
                                             FROM ".$CFG->prefix."domoscio
                                            WHERE course IN ($courselist)");

        $instancelist = array();
        foreach($instances as $instance)
        {
            $instancelist[] = $instance->id;
        }

        $instancelist = join(',', $instancelist);
        $kn_students = $DB->get_records_sql("SELECT *
                                               FROM ".$CFG->prefix."knowledge_node_students
                                         INNER JOIN ".$CFG->prefix."knowledge_nodes
                                         ON ".$CFG->prefix."knowledge_nodes.`knowledge_node_id` = ".$CFG->prefix."knowledge_node_students.`knowledge_node_id`
                                              WHERE ".$CFG->prefix."knowledge_node_students.`user` = $USER->id
                                                AND ".$CFG->prefix."knowledge_nodes.`active` IS NULL
                                                 OR ".$CFG->prefix."knowledge_nodes.`active` = '1'
                                                AND ".$CFG->prefix."knowledge_node_students.`instance` IN ($instancelist)");

        foreach($kn_students as $kn_student)
        {
            $rest = new domoscio_client();
            $result = json_decode($rest->setUrl($config, 'knowledge_node_students', $kn_student->kn_student_id)->get());

            if(strtotime($result->next_review_at) < time())
            {
                $list[] = $result->knowledge_node_id;
            }
        }
    }

    return $list;
}

/* This function displays tests interface */
function display_questions($question, $resource_type)
{
    if($question->qtype == "calculated" || $question->qtype == "numerical" || $question->qtype == "shortanswer")
    {
        $display = get_inputanswer($question, $resource_type);
    }
    elseif($question->qtype == "multichoice" || $question->qtype == "calculatedmulti" || $question->qtype == "truefalse")
    {
        $display = get_multichoiceanswer($question, $resource_type);
    }
    elseif($question->qtype == "multianswer")
    {
        $display = get_multianswer($question, $resource_type);
    }
    elseif($question->qtype == "match")
    {
        $display = get_match($question, $resource_type);
    }

    $qspan = html_writer::start_span('qno') . $question->id . html_writer::end_span();
    $qheader = html_writer::tag('h3', "Question ".$qspan, array('class' => 'no'));

    $qcontent = html_writer::tag('div', $display, array('class' => 'formulation'));

    $output = html_writer::tag('div', $qheader, array('class' => 'info'));
    $output .= html_writer::tag('div', $qcontent, array('class' => 'content'));
    $output = html_writer::tag('div', $output, array('class' => 'que '.$question->qtype.' deferredfeedback notyetanswered'));

    return $output;
}

/* This function retrives all answers for the question in params */
function get_answers($qnum, $resource_type)
{
    global $CFG, $DB;

    if($resource_type == "scorm") // If question stored in cell test tables
    {
        $sqlanswers = "SELECT ".$CFG->prefix."propositions.`content` as `answer`
                         FROM ".$CFG->prefix."propositions
                   INNER JOIN ".$CFG->prefix."proposition_lists
                           ON ".$CFG->prefix."proposition_lists.`id` = ".$CFG->prefix."propositions.`proposition_list_id`
                        WHERE ".$CFG->prefix."proposition_lists.`cell_question_id` = $qnum";
    }
    else // else if stored in Quiz Moodle tables
    {
        $sqlanswers = "SELECT *
                         FROM ".$CFG->prefix."question_answers
                        WHERE `question` = $qnum";
    }
    $answers = $DB->get_records_sql($sqlanswers);

    return $answers;
}

/* Displays text input for simple text questions */
function get_inputanswer($question)
{
    $qlabel = html_writer::tag('label', get_string('answer', 'domoscio'), array('for' => 'q0:'.$question->id.'_answer'));
    $qspan = html_writer::start_span('answer') . html_writer::tag('input', '', array('id' => 'q0:'.$question->id.'_answer', 'type' => 'text', 'size' => '80', 'name' => 'q0:'.$question->id.'_answer')) . html_writer::end_span();

    $output = html_writer::tag('div', '<p>'.$question->questiontext.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('div', $qlabel . $qspan, array('class' => 'ablock'));

    return $output;
}

/* Displays multichoice questions interface */
function get_multichoiceanswer($question, $resource_type)
{
    $i = 0;

    $radio = array();

    $answers_list = get_answers($question->id, $resource_type);

    foreach($answers_list as $answer)
    {
        $qinput = html_writer::tag('input', '', array('id' => 'q0:'.$question->id.'_answer', 'type' => 'radio', 'value' => $i, 'name' => 'q0:'.$question->id.'_answer'));
        $qlabel = html_writer::tag('label', strip_tags($answer->answer), array('for' => 'q0:'.$question->id.'_answer'));

        $radio[] = html_writer::tag('div', $qinput . $qlabel, array('class' => 'r0'));
        $i++;
    }
    $radio_fetched = implode("", $radio);

    $qablock = html_writer::tag('div', get_string('select_answer', 'domoscio'), array('class' => 'prompt')) . html_writer::tag('div', $radio_fetched, array('class' => 'answer'));
    $output = html_writer::tag('div', '<p>'.$question->questiontext.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('div', $qablock, array('class' => 'ablock'));

    return $output;
}

/* Displays multianswer test interface */
function get_multianswer($question, $resource_type)
{
    global $CFG, $DB;

    // Retrives all subquestions
    $result = array();
    $i = 1;

    if($resource_type == "scorm") // If data stored in cell test tables
    {
        $answers = $DB->get_records_sql("SELECT ".$CFG->prefix."propositions.`content` as `answer`, ".$CFG->prefix."propositions.`proposition_list_id`, ".$CFG->prefix."proposition_lists.`cell_question_type` FROM ".$CFG->prefix."propositions
                                     INNER JOIN ".$CFG->prefix."proposition_lists
                                             ON ".$CFG->prefix."proposition_lists.`id` = ".$CFG->prefix."propositions.`proposition_list_id`
                                          WHERE ".$CFG->prefix."proposition_lists.`cell_question_id` = $question->id");

        $j = reset($answers);

        foreach($answers as $answer)
        {
            $result[$answer->cell_question_type.$answer->proposition_list_id][] = $answer->answer;
        }
    }
    else // else if stored in Moodle Quiz tables
    {
        $answers = $DB->get_records_sql("SELECT ".$CFG->prefix."question_answers.`id`, ".$CFG->prefix."question.`qtype`, ".$CFG->prefix."question_answers.`answer`, ".$CFG->prefix."question_answers.`question`
                                           FROM ".$CFG->prefix."question
                                     INNER JOIN ".$CFG->prefix."question_answers
                                             ON ".$CFG->prefix."question.`id` = ".$CFG->prefix."question_answers.`question`
                                          WHERE ".$CFG->prefix."question.`parent` = $question->id");

        $j = reset($answers);
        foreach($answers as $answer)
        {
            $result[$answer->qtype.$answer->question][] = $answer->answer;
        }
    }

    // Parse the text for each holes and replace by HTML select
    preg_match_all("/({#.*?})/", $question->questiontext, $patterns);
    $i = 0;
    foreach($patterns[0] as $pattern)
    {
        $patterns[0][$i] = "'".$patterns[0][$i]."'";
        $i++;
    }

    $replacements = array();
    $i = 1;
    if($resource_type == "scorm"){$j = $j->proposition_list_id;}else{$j = $j->question;}

    foreach($result as $key => $hole)
    {
        if($key == "multichoice".$j)
        {
            $options = array();

            foreach($hole as $k => $option)
            {
                $options[] = html_writer::tag('option', $option, array('value' => $option));
            }

            shuffle($options);
            array_unshift($options , "<option value=''></option>");

            $options_fetched = implode("", $options);

            $qlabel = html_writer::tag('label', get_string('answer', 'domoscio'), array('for' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'class' => 'subq accesshide'));
            $qselect = html_writer::tag('select', $options_fetched, array('id' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'class' => 'select menuq0:'.$question->id.'_sub'.$i.'_answer', 'name' => 'q0:'.$question->id.'_sub'.$i.'_answer'));

            $replacements[] = html_writer::start_span('subquestion') . $qlabel . $qselect . html_writer::end_span();
        }
        elseif($key == "shortanswer".$j)
        {
            $qlabel = html_writer::tag('label', get_string('answer', 'domoscio'), array('for' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'class' => 'subq accesshide'));
            $qinput = html_writer::tag('input', '', array('id' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'type' => 'text', 'size' => '7', 'name' => 'q0:'.$question->id.'_sub'.$i.'_answer'));
            $replacements[] = html_writer::start_span('subquestion') . $qlabel . $qinput . html_writer::end_span();
        }
        $i++;
        $j++;
    }

    return preg_replace($patterns[0], $replacements, $question->questiontext);
}

/* Displays match questions test interface */
function get_match($question, $resource_type)
{
    global $CFG, $DB;

    $options = $lists = $subquestions = array();
    if($resource_type == "scorm")
    {
        $proplists = $DB->get_records_sql("SELECT ".$CFG->prefix."propositions.`id`, ".$CFG->prefix."propositions.`content` as `answertext`, ".$CFG->prefix."propositions.`proposition_list_id`
                                             FROM ".$CFG->prefix."propositions
                                       INNER JOIN ".$CFG->prefix."proposition_lists
                                               ON ".$CFG->prefix."proposition_lists.`id` = ".$CFG->prefix."propositions.`proposition_list_id`
                                            WHERE ".$CFG->prefix."proposition_lists.`cell_question_id` = $question->id");

    foreach($proplists as $proplist)
    {
        $lists[$proplist->proposition_list_id][] = $proplist->answertext;
    }

    foreach($lists as $list)
    {
        $pairs = new stdClass;
        $pairs->questiontext = $list[0];
        $pairs->answertext = $list[1];
        $subquestions[] = $pairs;
    }

    }
    else
    {
        $subquestions = $DB->get_records('qtype_match_subquestions', array('questionid' => $question->id), '', '*');
    }

    foreach($subquestions as $k => $subquestion)
    {
        $options[] = html_writer::tag('option', $subquestion->answertext, array('value' => $subquestion->answertext));
    }

    shuffle($options);
    array_unshift($options , "<option value=''>".get_string('choose', 'domoscio')."</option>");

    $options_fetched = implode("", $options);

    $table = array();
    $i = 1;

    foreach($subquestions as $subquestion)
    {
        $tdselect = html_writer::tag('select', $options_fetched, array('id' => 'menuq0:'.$question->id.'_sub'.$i, 'class' => 'select menuq0:'.$question->id.'_sub'.$i, 'name' => 'q0:'.$question->id.'_sub'.$i));
        $tdcontrol = html_writer::tag('label', 'Answer :', array('for' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'class' => 'subq accesshide')) . $tdselect;
        $rowcontent = html_writer::tag('td', '<p>'.$subquestion->questiontext.'</p>', array('class' => 'text')) . html_writer::tag('td', $tdcontrol, array('class' => 'control'));

        $table[] = html_writer::tag('tr', $rowcontent, array('class' => 'r0'));
        $i++;
    }

    $table_fetched = implode("", $table);

    $output = html_writer::tag('div', '<p>'.$question->questiontext.'</p>', array('class'=>'qtext'));
    $output .= html_writer::tag('table', '<tbody>'.$table_fetched.'</tbody>', array('class' => 'answer'));

    return $output;
}

/*----------------- RESULTATS ----------------*/
/* This function retrives only right answers */
function get_right_answers($qnum, $resource_type)
{
    global $CFG, $DB;

    if($resource_type == "scorm")
    {
        $sqlanswers = "SELECT ".$CFG->prefix."propositions.`content` as `answer` FROM ".$CFG->prefix."propositions
                   INNER JOIN ".$CFG->prefix."proposition_lists
                           ON ".$CFG->prefix."proposition_lists.`id` = ".$CFG->prefix."propositions.`proposition_list_id`
                        WHERE ".$CFG->prefix."proposition_lists.`cell_question_id` = $qnum
                          AND ".$CFG->prefix."propositions.`right` = 1";
    }
    else
    {
        $sqlanswers = "SELECT *
                         FROM ".$CFG->prefix."question_answers
                        WHERE `question` = $qnum
                          AND `fraction` = 1";
    }

    $answers = $DB->get_record_sql($sqlanswers);
    return $answers;
}

/*  Displays correction for simple text question */
function get_inputresult($question, $post, $resource_type)
{
    $result = new stdClass;
    $answer = get_right_answers($question->id, $resource_type);

    if($post['q0:'.$question->id.'_answer'] != $answer->answer)
    {
        $class = 'incorrect';
        $result->score = 0;
    }
    else
    {
        $class = 'correct';
        $result->score = 100;
    }
    $qlabel = html_writer::tag('label', get_string('answer', 'domoscio'), array('for' => 'q0:'.$question->id.'_answer'));
    $qspan = html_writer::start_span('answer')
            .html_writer::tag('input', '', array('class' => $class, 'id' => 'q0:'.$question->id.'_answer', 'type' => 'text', 'value' => $post['q0:'.$question->id.'_answer'], 'readonly' => 'readonly', 'size' => '80', 'name' => 'q0:'.$question->id.'_answer'))
            .html_writer::end_span();
    $qablock = $qlabel . $qspan;
    $divanswer = html_writer::tag('div', get_string('correction', 'domoscio').$answer->answer, array('class' => 'rightanswer'));

    $output = html_writer::tag('div', '<p>'.$question->questiontext.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('div', $qablock, array('class' => 'ablock'));
    $output .= html_writer::tag('div', $divanswer, array('class' => 'outcome'));

    $result->output = $output;
    return $result;
}

/*  Displays correction for multi choice question */
function get_multichoiceresult($question, $post, $resource_type)
{
    $result = new stdClass;
    $i = 0;

    $radio = array();

    $answers_list = get_answers($question->id, $resource_type);

    $rightanswer = get_right_answers($question->id, $resource_type);

    foreach($answers_list as $answer)
    {
        $class = null;

        if($i == $post['q0:'.$question->id.'_answer'])
        {
            $checkradio = "checked";
            if($answer->answer !== $rightanswer->answer)
            {
                $class = "incorrect";
                $result->score = 0;
            }
            else
            {
                $class = "correct";
                $result->score = 100;
            }
        }
        else
        {
            $checkradio = null;
        }
        $qinput = html_writer::tag('input', '', array('disabled' => 'disabled', 'id' => 'q0:'.$question->id.'_answer', 'type' => 'radio', 'value' => $i, 'name' => 'q0:'.$question->id.'_answer ', 'checked' => $checkradio));
        $qlabel = html_writer::tag('label', strip_tags($answer->answer), array('for' => 'q0:'.$question->id.'_answer'));
        $radio[] = html_writer::tag('div', $qinput . $qlabel, array('class' => 'r0 '.$class));

        $i++;
    }
    $radio_fetched = implode("", $radio);

    $qablock = html_writer::tag('div', get_string('select_answer', 'domoscio'), array('class' => 'prompt')) . html_writer::tag('div', $radio_fetched, array('class' => 'answer'));
    $divanswer = html_writer::tag('div', get_string('correction', 'domoscio').$rightanswer->answer, array('class' => 'rightanswer'));

    $output = html_writer::tag('div', '<p>'.$question->questiontext.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('div', $qablock, array('class' => 'ablock'));
    $output .= html_writer::tag('div', $divanswer, array('class' => 'outcome'));

    $result->output = $output;
    return $result;
}

/*  Displays correction for multi answer question */
function get_multiresult($question, $post, $resource_type)
{
    global $CFG, $DB;
    $result = new stdClass;
    // Récupère les sous-questions d'un texte à trous
    $response = array();
    $i = 1;

    if($resource_type == "scorm")
    {
        $answers = $DB->get_records_sql("SELECT ".$CFG->prefix."propositions.`content` as `answer`, ".$CFG->prefix."propositions.`proposition_list_id`, ".$CFG->prefix."proposition_lists.`cell_question_type` FROM ".$CFG->prefix."propositions
                                     INNER JOIN ".$CFG->prefix."proposition_lists
                                             ON ".$CFG->prefix."proposition_lists.`id` = ".$CFG->prefix."propositions.`proposition_list_id`
                                          WHERE ".$CFG->prefix."proposition_lists.`cell_question_id` = $question->id
                                            AND ".$CFG->prefix."propositions.`right` = 1");

        $j = reset($answers);

        foreach($answers as $answer)
        {
            $response[$answer->cell_question_type.$answer->proposition_list_id][] = $answer->answer;
        }
    }
    else
    {
        $answers = $DB->get_records_sql("SELECT ".$CFG->prefix."question_answers.`id`, ".$CFG->prefix."question.`qtype`, ".$CFG->prefix."question_answers.`answer`, ".$CFG->prefix."question_answers.`question`
                                           FROM ".$CFG->prefix."question INNER JOIN ".$CFG->prefix."question_answers
                                             ON ".$CFG->prefix."question.`id` = ".$CFG->prefix."question_answers.`question`
                                          WHERE ".$CFG->prefix."question.`parent` = $question->id");

        $j = reset($answers);

        foreach($answers as $answer)
        {
            $response[$answer->qtype.$answer->question][] = $answer->answer;
        }
    }

    // Parse the text for each holes and replace by HTML select
    preg_match_all("/({#.*?})/", $question->questiontext, $patterns);
    $i = 0;
    foreach($patterns[0] as $pattern)
    {
        $patterns[0][$i] = "'".$patterns[0][$i]."'";
        $i++;
    }

    $replacements = array();
    $i = 1;
    if($resource_type == "scorm"){$j = $j->proposition_list_id;}else{$j = $j->question;}
    $subresult = 0;
    foreach($response as $key => $answer)
    {
        if(($post['q0:'.$question->id.'_sub'.$i.'_answer']) == $answer[0])
        {
            $class = "correct";
            $subresult += 1;
        }
        else
        {
            $class = "incorrect";
            $subresult += 0;
        }

        if($key == "multichoice".$j)
        {
            $qlabel = html_writer::tag('label', 'Answer :', array('for' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'class' => 'subq accesshide'));
            $qselect = html_writer::tag('select', '<option>'.$post['q0:'.$question->id.'_sub'.$i.'_answer'].'</option>', array('id' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'class' => 'select menuq0:'.$question->id.'_sub'.$i.'_answer '.$class, 'name' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'disabled' => 'disabled'));

            $replacements[] = html_writer::start_span('subquestion') . $qlabel . $qselect . html_writer::end_span();
        }
        elseif($key == "shortanswer".$j)
        {
            $qlabel = html_writer::tag('label', 'Answer :', array('for' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'class' => 'subq accesshide'));
            $qinput = html_writer::tag('input', '', array('class' => $class, 'id' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'value' => $post['q0:'.$question->id.'_sub'.$i.'_answer'], 'type' => 'text', 'size' => '7', 'name' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'readonly' => 'readonly', ));
            $replacements[] = html_writer::start_span('subquestion') . $qlabel . $qinput . html_writer::end_span();
        }
        $i++;
        $j++;


    }
    if(count($response) != $subresult){$result->score = 0;}else{$result->score = 100;}

    $display = preg_replace($patterns[0], $replacements, $question->questiontext);

    $result->output = $display;
    return $result;
}

/*  Displays correction for match question */
function get_matchresult($question, $post, $resource_type)
{
    global $CFG, $DB;
    $result = new stdClass;
    $options = $lists = $subquestions = $table = array();
    if($resource_type == "scorm")
    {
        $proplists = $DB->get_records_sql("SELECT ".$CFG->prefix."propositions.`id`, ".$CFG->prefix."propositions.`content` as `answertext`, ".$CFG->prefix."propositions.`proposition_list_id`
                                             FROM ".$CFG->prefix."propositions
                                       INNER JOIN ".$CFG->prefix."proposition_lists
                                               ON ".$CFG->prefix."proposition_lists.`id` = ".$CFG->prefix."propositions.`proposition_list_id`
                                            WHERE ".$CFG->prefix."proposition_lists.`cell_question_id` = $question->id");

        foreach($proplists as $proplist)
        {
            $lists[$proplist->proposition_list_id][] = $proplist->answertext;
        }

        foreach($lists as $list)
        {
            $pairs = new stdClass;
            $pairs->questiontext = $list[0];
            $pairs->answertext = $list[1];
            $subquestions[] = $pairs;
        }

    }
    else
    {
        $subquestions = $DB->get_records_sql("SELECT *
                                                FROM ".$CFG->prefix."qtype_match_subquestions
                                               WHERE `questionid` =".$question->id);
    }

    $i = 1;
    $subresult = 0;

    foreach($subquestions as $subquestion)
    {
        $class = null;
        if(($post['q0:'.$question->id.'_sub'.$i]) == $subquestion->answertext)
        {
            $class = "correct";
            $subresult += 1;
        }
        else
        {
            $class = "incorrect";
            $subresult += 0;
        }

        $tdselect = html_writer::tag('select', '<option>'.$post['q0:'.$question->id.'_sub'.$i].'</option>', array('id' => 'menuq0:'.$question->id.'_sub'.$i, 'class' => 'select '.$class.' menuq0:'.$question->id.'_sub'.$i, 'name' => 'q0:'.$question->id.'_sub'.$i, 'disabled' => 'disabled'));
        $tdcontrol = html_writer::tag('label', 'Answer :', array('for' => 'q0:'.$question->id.'_sub'.$i.'_answer', 'class' => 'subq accesshide')) . $tdselect;
        $rowcontent = html_writer::tag('td', '<p>'.$subquestion->questiontext.'</p>', array('class' => 'text')) . html_writer::tag('td', $tdcontrol, array('class' => 'control'));

        $table[] = html_writer::tag('tr', $rowcontent, array('class' => 'r0'));

        $i++;
    }

    $table_fetched = implode("", $table);

    $output = html_writer::tag('div', '<p>'.$question->questiontext.'</p>', array('class'=>'qtext'));
    $output .= html_writer::tag('table', '<tbody>'.$table_fetched.'</tbody>', array('class' => 'answer'));

    $result->output = $output;

    if(count($subquestions) != $subresult){$result->score = 0;}else{$result->score = 100;}

    return $result;
}

/*  Add or update event in users Moodle calendar  */
function create_event($domoscio, $course, $kn_student)
{
    global $DB, $CGF, $USER;

    $event = new stdClass;
    $event->name    = "Domoscio Rappel :".$domoscio->name;
    $event->description = "Vous avez un rappel à faire sur la ressource ";
    $event->courseid    = $course->id;
    $event->groupid     = 0;
    $event->userid      = $USER->id;
    $event->modulename  = 'domoscio';
    $event->instance    = $domoscio->id;
    $event->eventtype   = 'feedbackcloses';
    $event->timestart   = strtotime($kn_student->next_review_at);
    $event->visible     = instance_is_visible('domoscio', $domoscio);
    $event->timeduration    = 60;

    $check = $DB->get_record('event', array('instance' => $domoscio->id), '*');

    if(!empty($check))
    {
        $reminder = calendar_event::load($check->id);
        $reminder->update($event);
    }
    else
    {
        calendar_event::create($event);
    }
}

function plural($count)
{
    if(count($count) > 1)
    {
        return $plural = "s";
    }
    else
    {
        return $plural = "";
    }
}
