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
 * @package    mod_domoscio
 * @copyright  2015 Domoscio SA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/sdk/client.php');
require_once(dirname(dirname(__FILE__)).'/scorm/locallib.php');

defined('MOODLE_INTERNAL') || die();

define('DOMOSCIO_ULTIMATE_ANSWER', 42);

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

    // If course is not stored on API as knowledgegraph, create new one
    $check = $DB->get_records('domoscio_knowledge_graphs', array('courseid' => $domoscio->course), '', 'kgraphid');

    $rest = new mod_domoscio_client();

    if (count($check) > 0) {
        // Retrive existing knowledgegraph
        foreach ($check as $result) {
            $graphid = $result->kgraphid;
        }

        $graph = json_decode($rest->seturl($config, 'knowledge_graphs', $graphid)->get());
    } else {
        // Else create new one and store it in DB
        $json = json_encode(array('name' => strval($COURSE->fullname)));

        $graph = json_decode($rest->seturl($config, 'knowledge_graphs', null)->post($json));

        $graphid = $graph->id;
        $knowledgegraph = new stdClass;
        $knowledgegraph->courseid = $domoscio->course;
        $knowledgegraph->kgraphid = $graph->id;

        $knowledgegraph = $DB->insert_record('domoscio_knowledge_graphs', $knowledgegraph);
    }

    $rest = new mod_domoscio_client();

    // Create new parent knowledge node for this new instance
    $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                              'name' => strval($domoscio->resource)));

    $resource = json_decode($rest->seturl($config, 'knowledge_nodes', null)->post($json));

    $knowledgenode = new stdClass;

    $knowledgenode->resourceid = $domoscio->resource;
    $knowledgenode->knodeid = $resource->id;
    $knowledgenode->instance = null;
    $knowledgenode->id = $DB->insert_record('domoscio_knowledge_nodes', $knowledgenode);

    // Retrive resource id created by API and store it with new Domoscio instance
    $domoscio->resourceid = $resource->id;

    $linkedresource = domoscio_get_resource_info($resource->id);
    $domoscio->resourcetype = $linkedresource->modulename;

    $domoscio->id = $DB->insert_record('domoscio', $domoscio);

    // If linkedresource is SCORM package, create new knowledge node for each SCO
    if ($linkedresource->modulename == "scorm") {
        $scoes = domoscio_get_scorm_scoes($resource->id);

        domoscio_write_knowledge_nodes($scoes, $config, $resource, $graphid, $domoscio);
    }

    if ($linkedresource->modulename == "book") {
        $chapters = domoscio_get_book_chapters($resource->id);

        domoscio_write_knowledge_nodes($chapters, $config, $resource, $graphid, $domoscio);
    }

    if ($linkedresource->modulename == "lesson") {
        $contentpages = domoscio_get_lesson_content($resource->id);

        domoscio_write_knowledge_nodes($contentpages, $config, $resource, $graphid, $domoscio);
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

    $DB->delete_records('domoscio_knode_students', array('instance' => $domoscio->id));
    $DB->delete_records('domoscio_knode_questions', array('instance' => $domoscio->id));
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

/**
 * Retrive all datas from current Moodle, prepare json, send it to Domoscio API and
 * retrive datas to store in DB Moodle
 * @return void
 */
function domoscio_create_student() {
    global $USER, $DB;
    $config = get_config('domoscio');

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

    $rest = new mod_domoscio_client();

    $student = json_decode($rest->seturl($config, 'students', null)->post($json));

    // Plugin retrive uniqid and store id in DB
    $record = new stdClass();
    $record->userid = $USER->id;
    $record->uniqid = $student->id;
    $insert = $DB->insert_record('domoscio_userapi', $record, false);
}

/**
 * Assuming the student already logged in the Domoscio plugin, this function retrieves his knowledgenode_students
 * or create a new knowledgenode_students if student never reviewed the plugin
 * or retrieves his knowledgenode_students if another Domoscio instance is linked to the same resource
 *
 * @param \stdClass $config Domoscio plugin settings
 * @param \stdClass $domoscio the Domoscio instance data
 * @param \stdClass $check stored student data
 * @return \stdClass $knstudent the knowledge node student data
 */
function domoscio_manage_student($config, $domoscio, $check) {
    global $USER, $CFG, $DB;

    $rest = new mod_domoscio_client();

    // Retrive student data from API
    $student = json_decode($rest->seturl($config, 'students', $check->uniqid)->get());

    // Retrive all active knowledge nodes relative to this instance of Domoscio
    $knowledgenodes = $DB->get_records_select('domoscio_knowledge_nodes', "instance = :instance AND active <> 0", array('instance' => $domoscio->id));

    $knstudent = array();

    // Check if kn student exist for each knowledgenode, retrive data if so or create new one if not set
    foreach ($knowledgenodes as $kn) {
        if (!$knsquery = $DB->get_record('domoscio_knode_students', array('knodeid' => $kn->knodeid, 'user' => $USER->id))) {
            $jsonkn = json_encode(array('knowledge_node_id' => intval($kn->knodeid), 'student_id' => intval($student->id)));

            $kndata = json_decode($rest->seturl($config, 'knowledge_node_students', null)->post($jsonkn));

            // Get knowledgenodestudent created and store it into database
            $record = new stdClass();
            $record->user = $USER->id;
            $record->instance = $domoscio->id;
            $record->knodeid = $kn->knodeid;
            $record->knodestudentid = $kndata->id;
            $insert = $DB->insert_record('domoscio_knode_students', $record, false);
            $knsquery = $DB->get_record('domoscio_knode_students', array('knodeid' => $kn->knodeid, 'user' => $USER->id));
        }

        $knstudent[] = json_decode($rest->seturl($config, 'knowledge_node_students', $knsquery->knodestudentid)->get());

        // Check if API has results for this kn student, if not, search for results on Moddle DB, or invite to pass a first test
        $lastkn = end($knstudent);

        if ($lastkn->history == "") {
            // Retrive list of exercises (SCORM & Quiz) selected by course creator
            $questions = $DB->get_records('domoscio_knode_questions', array('instance' => $domoscio->id, 'knodeid' => $kn->knodeid), '', '*');
            $listquestions = $listscoes = $listlessonpages = array();
            $i = 0;
            foreach ($questions as $question) {
                if ($question->type == "quiz") {
                    $listquestions[$i] = $question->questionid;
                } else if ($question->type == "scorm") {
                    $listscoes[$i] = $question->questionid;
                } else if ($question->type == "lesson") {
                    $listlessonpages[$i] = $question->questionid;
                }
                $i++;
            }
            $listquestions = join(',', $listquestions);
            $listlessonpages = join(',', $listlessonpages);
            $scorescoes = $scorequestions = $scorelessons = "";

            if (!empty($listscoes)) {
                // Retrive data for each SCOes selected
                $scoredata = array();
                foreach ($listscoes as $sco) {
                    if ($tracks = scorm_get_tracks($sco, $USER->id)) {
                        $scoredata[] = $tracks->{"cmi.score.scaled"};
                    }
                }

                if (!empty($scoredata)) {
                    $scoreavg = array_sum($scoredata) / count($scoredata);
                    $scorescoes = (round($scoreavg * 100));
                }
            }

            if (!empty($listquestions)) {
                // Retrive data for each Quiz module questions selected
                $queryparams = array('userid' => $USER->id);
                list($insql, $inparams) = $DB->get_in_or_equal($listquestions, SQL_PARAMS_NAMED);

                $sql = "SELECT AVG({question_attempt_steps}.`fraction`) AS score
                          FROM {question_attempt_steps}
                    INNER JOIN {question_attempts}
                            ON {question_attempts}.`id` = {question_attempt_steps}.`questionattemptid`
                         WHERE {question_attempt_steps}.`userid` = :userid
                           AND {question_attempt_steps}.`sequencenumber` = 2
                           AND {question_attempts}.`questionid` $insql
                        HAVING MAX({question_attempts}.`timemodified`)";

                $params = array_merge($inparams, $queryparams);
                $scoredata = $DB->get_records_sql($sql, $params);
                $scoredata = array_shift($scoredata);

                if (isset($scoredata->score)) {
                    $scorequestions = (round($scoredata->score * 100));
                }
            }

            if (!empty($listlessonpages)) {
                // Retrive scores for each Lesson pages selected as questions
                $queryparams = array('userid' => $USER->id);
                list($insql, $inparams) = $DB->get_in_or_equal($listlessonpages, SQL_PARAMS_NAMED);

                $sql = "SELECT AVG(`correct`) AS score
                          FROM {lesson_attempts}
                         WHERE `userid` = :userid
                           AND `pageid` $insql";

                $params = array_merge($inparams, $queryparams);
                $scoredata = $DB->get_records_sql($sql, $params);
                $scoredata = array_shift($scoredata);

                if (isset($scoredata->score)) {
                    $scorelessons = (round($scoredata->score * 100));
                }
            }

            // If scores exist, send them to API and retrive next due date
            $total = 0;
            if ($scorequestions !== "") {
                $total++;
            }
            if ($scorescoes !== "") {
                $total++;
            }
            if ($scorelessons !== "") {
                $total++;
            }

            $scorejson = "";

            if ($total > 0) {
                $scoreglobal = ($scorequestions + $scorescoes + $scorelessons) / $total;
                $scorejson = json_encode(array('knowledge_node_student_id' => intval($lastkn->id),
                                                                   'value' => intval($scoreglobal)));
            }

            if ($scorejson !== "") {
                $sending = $rest->seturl($config, 'results', null)->post($scorejson);

                array_pop($knstudent);

                $knstudent[] = json_decode($rest->seturl($config, 'knowledge_node_students', $lastkn->id)->get());

                $newevent = domoscio_create_event($domoscio, end($knstudent));
            }
        }
    }

    return $knstudent;
}

/**
 * Retrives course modules data and retrun display and useful datas
 *
 * @param \int $knowledgenode the knowledgenode data stored in Moodle DB
 * @return \stdClass $return various datas regarding the course module linked
 */
function domoscio_get_resource_info($knowledgenode) {

    global $DB, $CFG, $OUTPUT;

    $query = "SELECT {course_modules}.`module`, {course_modules}.`instance`, {course_modules}.`id`
                FROM {course_modules}
          INNER JOIN {domoscio_knowledge_nodes}
                  ON {course_modules}.`id` = {domoscio_knowledge_nodes}.`resourceid`
               WHERE {domoscio_knowledge_nodes}.`knodeid` = :knowledgenode";

    $resource = $DB->get_record_sql($query, array('knowledgenode' => $knowledgenode));

    $modulename = null;

    switch($resource->module) {
        case 3:
            $modulename = "book";
            break;

        case 13:
            $modulename = "lesson";
            break;

        case 15:
            $modulename = "page";
            break;

        case 18:
            $modulename = "scorm";
            break;
    }

    $moduleinfo = $DB->get_record($modulename, array('id' => $resource->instance), 'name');

    $return = new stdClass();

    $return->display = html_writer::img($OUTPUT->pix_url('icon',
                                                         $modulename,
                                                         $modulename,
                                                         array('class' => 'icon')),
                                                         '',
                                                         array('class' => 'activityicon')) . " <span>$moduleinfo->name</span>";
    $return->modulename = $modulename;
    $return->instance = $resource->instance;
    $return->url = "$CFG->wwwroot/mod/$modulename/view.php?id=$resource->id";
    $return->cm = $resource->id;

    if ($modulename == "scorm") {
        $sco = $DB->get_record_sql("SELECT *
                                    FROM {scorm_scoes}
                              INNER JOIN {domoscio_knowledge_nodes}
                                      ON {domoscio_knowledge_nodes}.`childid` = {scorm_scoes}.`id`
                                   WHERE {domoscio_knowledge_nodes}.`knodeid` = :knowledgenode",
                                   array('knowledgenode' => $knowledgenode)
                                  );

        if (!empty($sco)) {
            $return->sco = " / ".$sco->title;
            $return->sco_id = $sco->childid;
        } else {
            $return->sco = $return->sco_id = " ";
        }
    } else {
        $return->sco = $return->sco_id = " ";
    }

    return $return;
}

/**
 * Retrive all SCOes in SCORM package by knowledge_node id
 *
 * @param \int $kn the knowledgenode id
 * @return \stdClass $scoes all scoes in SCORM package corresponding to knowledgenode
 */
function domoscio_get_scorm_scoes($kn) {
    global $DB, $CFG;

    $instance = domoscio_get_resource_info($kn)->instance;

    $scoes = $DB->get_records_sql("SELECT *
                                   FROM {scorm_scoes}
                                  WHERE `scorm` = :instance
                                    AND `scormtype` = 'sco'",
                                  array('instance' => $instance)
                                 );

    return $scoes;
}

/**
 * Retrive all chapters in Moodle book module by its id
 *
 * @param \stdClass $cm the linked book id
 * @return \stdClass $chapters all chapters in book
 */
function domoscio_get_book_chapters($cm) {
    global $DB, $CFG;

    $instance = domoscio_get_resource_info($cm)->instance;

    $chapters = $DB->get_records_sql("SELECT *
                                        FROM {book_chapters}
                                       WHERE `bookid` = :instance",
                                     array('instance' => $instance)
                                    );

    return $chapters;
}

/**
 * Retrive all content pages in Moodle lesson module by its id
 *
 * @param \stdClass $cm the linked book id
 * @return \stdClass $chapters all chapters in book
 */
function domoscio_get_lesson_content($cm) {
    global $DB, $CFG;

    $instance = domoscio_get_resource_info($cm)->instance;

    $contents = $DB->get_records_sql("SELECT *
                                        FROM {lesson_pages}
                                       WHERE `lessonid` = :instance
                                         AND `qtype` = 20",
                                       array('instance' => $instance)
                                    );

    return $contents;
}

/**
 * Display list of all activity modules for course creator at Question linking
 *
 * @param \stdClass $activity the course module embbeding exercises
 * @param \string $moduletype the type of activity module
 * @param \in $kn the knowledge node id of the notion belonging to Domoscio instance
 * @param \stdClass $cm the Domoscio course module
 * @return \var $listitem the html writer for activities list
 */
function domoscio_display_activities_list($activity, $moduletype, $kn, $cm) {
    global $DB, $CFG, $OUTPUT;

    switch($moduletype) {
        case "lesson":
            $moduleid = 13;
            $cap = 'mod/lesson:manage';
            break;

        case "quiz":
            $moduleid = 16;
            $cap = 'mod/quiz:manage';
            break;

        case "scorm":
            $moduleid = 18;
            $cap = 'mod/scorm:viewreport';
            break;
    }

    $activitycm = $DB->get_record('course_modules', array('instance' => $activity->id, 'module' => $moduleid), '*');

    if ($activitycm) {
        $activitycontext = context_module::instance($activitycm->id);
    }

    if (has_capability($cap, $activitycontext)) {
        $icon = html_writer::tag('img', '', array('src' => $OUTPUT->pix_url('icon', $moduletype, $moduletype, array('class' => 'smallicon navicon')),
                                                 'class' => 'icon', 'alt' => 'disable'));
        $url = html_writer::link("$CFG->wwwroot/mod/domoscio/linkto.php?id=$cm->id&notion=$kn&exo=".$moduletype."_".$activity->id, $icon." ".$activity->name);
        $listitem = html_writer::tag('p', $url, array('class' => ''));
    }

    return $listitem;
}

/**
 * Create and store knowledge node for each notion contained in module
 *
 * @param \stdClass $notions the notions retrived from selected module
 * @param \stdClass $config domoscio client config datas
 * @param \stdClass $resource datas from mod form
 * @param \stdClass $graphid the knowledgegraph id of the active course
 * @param \stdClass $domoscio the domoscio instance being stored datas
 * @return void
 */
function domoscio_write_knowledge_nodes($notions, $config, $resource, $graphid, $domoscio) {
    global $DB, $CFG;
    $rest = new mod_domoscio_client();

    foreach ($notions as $notion) {
        // Create new knowledge node
        $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                                    'name' => strval($notion->title)));

        $kn = json_decode($rest->seturl($config, 'knowledge_nodes', null)->post($json));

        // Create knowledge edges
        $json = json_encode(array('knowledge_graph_id' => strval($graphid),
                                    'source_node_id' => strval($resource->id),
                                    'destination_node_id' => strval($kn->id)));

        $knedge = json_decode($rest->seturl($config, 'knowledge_edges', null)->post($json));

        // Inscrit le knowledge node du SCO en DB
        $knowledgenode = new stdClass;
        $knowledgenode->instance = $domoscio->id;
        $knowledgenode->knodeid = $kn->id;
        $knowledgenode->resourceid = $domoscio->resource;
        $knowledgenode->childid = $notion->id;

        $knowledgenode = $DB->insert_record('domoscio_knowledge_nodes', $knowledgenode);
    }
}

/**
 * This function counts all plugins where student is enrolled and due date has arrived
 *
 * @param \stdClass $config the Domoscio config data
 * @return \stdClass $list all tests to do
 */
function domoscio_count_tests($config) {
    global $DB, $USER, $CFG;

    // Check courses student is enrolled
    $courseenrol = $DB->get_records_sql("SELECT `courseid`
                                          FROM {enrol}
                                    INNER JOIN {user_enrolments}
                                            ON {user_enrolments}.`enrolid` = {enrol}.`id`
                                         WHERE {user_enrolments}.`userid` = :userid",
                                       array('userid' => $USER->id)
                                       );

    $courselist = array();
    foreach ($courseenrol as $course) {
        $courselist[] = $course->courseid;
    }

    $list = array();

    // Retrives due date for these courses
    if (!empty($courselist)) {
        list($insql, $inparams) = $DB->get_in_or_equal($courselist);

        $sql = "SELECT id
                FROM {domoscio}
                WHERE course $insql";

        $instances = $DB->get_records_sql($sql, $inparams);

        $instancelist = array();
        foreach ($instances as $instance) {
            $instancelist[] = $instance->id;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($instancelist, SQL_PARAMS_NAMED);
        $queryparams = array('userid' => $USER->id);

        $sql = "SELECT *
                FROM {domoscio_knode_students}
                INNER JOIN {domoscio_knowledge_nodes}
                ON {domoscio_knowledge_nodes}.`knodeid` = {domoscio_knode_students}.`knodeid`
                WHERE {domoscio_knode_students}.`user` = :userid
                AND ({domoscio_knowledge_nodes}.`active` IS NULL
                    OR {domoscio_knowledge_nodes}.`active` = '1')
                AND {domoscio_knode_students}.`instance` $insql";

        $params = array_merge($inparams, $queryparams);
        $knstudents = $DB->get_records_sql($sql, $params);

        foreach ($knstudents as $knstudent) {
            $rest = new mod_domoscio_client();
            $result = json_decode($rest->seturl($config, 'knowledge_node_students', $knstudent->knodestudentid)->get());

            if (strtotime($result->next_review_at) < time() && $result->next_review_at != null) {
                $list[] = $result->knowledge_node_id;
            }
        }
    }

    return $list;
}

/**
 * This function displays tests interface
 *
 * @param \stdClass $question the selected question data
 * @return \var $ouptut the question display
 */
function domoscio_display_questions($question) {
    $qtype = domoscio_get_qtype($question);

    if ($qtype == "calculated" || $qtype == "numerical" || $qtype == "shortanswer") {
        $display = domoscio_get_input_answers($question);
    } else if ($qtype == "multichoice" || $qtype == "truefalse") {
        $display = domoscio_get_multichoice_answer($question);
    } else if ($qtype == "match") {
        $display = domoscio_get_match($question);
    }

    $qspan = html_writer::start_span('qno') . $question->id . html_writer::end_span();
    $qheader = html_writer::tag('h3', "Question ".$qspan, array('class' => 'no'));

    $qcontent = html_writer::tag('div', $display, array('class' => 'formulation'));

    $output = html_writer::tag('div', $qheader, array('class' => 'info'));
    $output .= html_writer::tag('div', $qcontent, array('class' => 'content'));
    $output = html_writer::tag('div', $output, array('class' => 'que '.$question->qtype.' deferredfeedback notyetanswered'));
    return $output;
}

/**
 * This function retrives all answers for the question in params
 *
 * @param \stdClass $question the selected question data
 * @return \string $qtype the question type
 */
function domoscio_get_qtype($question) {
    switch($question->qtype) {
        case 1:
            $qtype = "shortanswer";
            break;

        case 2:
            $qtype = "truefalse";
            break;

        case 3:
            $qtype = "multichoice";
            break;

        case 5:
            $qtype = "match";
            break;

        case 8:
            $qtype = "numerical";
            break;

        case 10:
            $qtype = "essay";
            break;
    }
    return $qtype;
}

/**
 * This function retrives all answers for the question in params
 *
 * @param \int $qnum the selected question id
 * @return \stdClass $answers the answers list
 */
function domoscio_get_answers($qnum) {
    global $CFG, $DB;

    $sqlanswers = "SELECT *
                     FROM {lesson_answers}
                    WHERE `pageid` = :qnum";

    $answers = $DB->get_records_sql($sqlanswers, array('qnum' => $qnum));

    foreach ($answers as $answer) {
        $answer->fraction = $answer->grade;
    }

    return $answers;
}

/**
 * Displays text input for simple text questions
 *
 * @param \stdClass $question the selected question data
 * @return \var $output the interface display
 */
function domoscio_get_input_answers($question) {

    $qlabel = html_writer::tag('label', get_string('answer', 'domoscio'), array('for' => 'q0:'.$question->id.'_answer'));
    $qspan = html_writer::start_span('answer').
             html_writer::tag('input', '', array('id' => 'q0:'.$question->id.'_answer', 'type' => 'text', 'size' => '80', 'name' => 'q0:'.$question->id.'_answer')).
             html_writer::end_span();

    $output = html_writer::tag('div', '<p>'.$question->contents.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('div', $qlabel . $qspan, array('class' => 'ablock'));

    return $output;
}

/**
 * Displays multichoice questions interface
 *
 * @param \stdClass $question the selected question data
 * @return \var $output the interface display
 */
function domoscio_get_multichoice_answer($question) {
    global $DB;
    $i = 0;

    $correctanswers = $DB->get_records('lesson_answers', array('pageid' => $question->id, 'grade' => '100'), '', '*');
    if (count($correctanswers) > 1) {
        $single = 0;
    } else {
        $single = 1;
    }

    $answers = array();
    $answerslist = domoscio_get_answers($question->id);

    foreach ($answerslist as $answer) {
        if (isset($single)) {
            if ($single == 1) {
                $inputtype = "radio";
                $qinput = html_writer::tag('input', '', array('id' => 'q0:'.$question->id.'_answer',
                                                              'type' => $inputtype,
                                                              'value' => $i,
                                                              'name' => 'q0:'.$question->id.'_answer'));
                $qlabel = html_writer::tag('label', strip_tags($answer->answer), array('for' => 'q0:'.$question->id.'_answer'));

            } else if ($single == 0) {
                $inputtype = "checkbox";
                $qinput = html_writer::tag('input', '', array('id' => 'q0:'.$question->id.'_choice'.$i,
                                                              'type' => $inputtype,
                                                              'value' => $i,
                                                              'name' => 'q0:'.$question->id.'_choice'.$i));
                $qlabel = html_writer::tag('label', strip_tags($answer->answer), array('for' => 'q0:'.$question->id.'_choice'.$i));
            }
        } else {
            $inputtype = "radio";
            $qinput = html_writer::tag('input', '', array('id' => 'q0:'.$question->id.'_answer',
                                                          'type' => $inputtype,
                                                          'value' => $i,
                                                          'name' => 'q0:'.$question->id.'_answer'));
            $qlabel = html_writer::tag('label', strip_tags($answer->answer), array('for' => 'q0:'.$question->id.'_answer'));
        }
        $answers[] = html_writer::tag('div', $qinput . $qlabel, array('class' => 'r0'));
        $i++;
    }
    $answersfetched = implode("", $answers);

    $qablock = html_writer::tag('div', get_string('select_answer', 'domoscio'), array('class' => 'prompt')).
               html_writer::tag('div', $answersfetched, array('class' => 'answer'));
    $output = html_writer::tag('div', '<p>'.$question->contents.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('div', $qablock, array('class' => 'ablock'));

    return $output;
}

/**
 * Displays match questions interface
 *
 * @param \stdClass $question the selected question data
 * @return \var $output the interface display
 */
function domoscio_get_match($question) {
    global $CFG, $DB;

    $options = $lists = $subquestions = array();

    $subquestions = $DB->get_records_sql("SELECT *
                                            FROM {lesson_answers}
                                           WHERE `pageid` = :pid
                                             AND `response` IS NOT NULL",
                                         array('pid' => $question->id)
                                        );

    foreach ($subquestions as $k => $subquestion) {
        $options[] = html_writer::tag('option', $subquestion->response, array('value' => $subquestion->response));
        $subquestion->questiontext = $subquestion->answer;
    }

    shuffle($options);
    array_unshift($options , "<option value=''>".get_string('choose', 'domoscio')."</option>");

    $optionsfetched = implode("", $options);

    $table = array();
    $i = 1;

    foreach ($subquestions as $subquestion) {
        $tdselect = html_writer::tag('select', $optionsfetched, array('id' => 'menuq0:'.$question->id.'_sub'.$i,
                                                                   'class' => 'select menuq0:'.$question->id.'_sub'.$i,
                                                                    'name' => 'q0:'.$question->id.'_sub'.$i));
        $tdcontrol = html_writer::tag('label', 'Answer :', array('for' => 'q0:'.$question->id.'_sub'.$i.'_answer',
                                                               'class' => 'subq accesshide')) . $tdselect;
        $rowcontent = html_writer::tag('td', '<p>'.$subquestion->questiontext.'</p>', array('class' => 'text')).
                      html_writer::tag('td', $tdcontrol, array('class' => 'control'));

        $table[] = html_writer::tag('tr', $rowcontent, array('class' => 'r0'));
        $i++;
    }

    $tablefetched = implode("", $table);

    $output = html_writer::tag('div', '<p>'.$question->contents.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('table', '<tbody>'.$tablefetched.'</tbody>', array('class' => 'answer'));

    return $output;
}

/*----------------- RESULTS ----------------*/
/**
 * This function retrive right answers relative to their question
 *
 * @param \int $qnum the selected question id
 * @param \bool $single if question has one or many right answers
 * @return \stdClass $answers the right answer(s) list
 */
function domoscio_get_right_answers($qnum, $single) {
    global $CFG, $DB;

    if (isset($single) && $single == 0) {
        $answers = $DB->get_records_select('lesson_answers', "pageid = :qnum AND grade > 0", array('qnum' => $qnum));
    } else {
        $answers = $DB->get_records_select('lesson_answers', "pageid = :qnum AND grade = 100", array('qnum' => $qnum));
    }

    return $answers;
}

/**
 * Displays correction for simple text question
 *
 * @param \stdClass $question the selected question data
 * @param \mixed $submitted the answer given by student
 * @return \var $result the correction display
 */
function domoscio_get_input_result($question, $submitted) {
    $result = new stdClass;
    $rightanswer = domoscio_get_right_answers($question->id, 1);

    foreach ($rightanswer as $answer) {
        if (strtolower($submitted->{'q0:'.$question->id.'_answer'}) != strtolower($answer->answer)) {
            $class = 'incorrect';
            $result->score = 0;
        } else {
            $class = 'correct';
            $result->score = 100;
            break;
        }
    }
    $qlabel = html_writer::tag('label', get_string('answer', 'domoscio'), array('for' => 'q0:'.$question->id.'_answer'));
    $qspan = html_writer::start_span('answer')
            .html_writer::tag('input', '', array('class' => $class,
                                                    'id' => 'q0:'.$question->id.'_answer',
                                                  'type' => 'text',
                                                 'value' => s($submitted->{'q0:'.$question->id.'_answer'}),
                                              'readonly' => 'readonly',
                                                  'size' => '80',
                                                  'name' => 'q0:'.$question->id.'_answer'))
            .html_writer::end_span();
    $qablock = $qlabel . $qspan;
    $divanswer = html_writer::tag('div', get_string('correction', 'domoscio').$answer->answer, array('class' => 'rightanswer'));

    $output = html_writer::tag('div', '<p>'.$question->contents.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('div', $qablock, array('class' => 'ablock'));
    $output .= html_writer::tag('div', $divanswer, array('class' => 'outcome'));

    $result->output = $output;
    return $result;
}

/**
 * Displays correction for multi choice question
 *
 * @param \stdClass $question the selected question data
 * @param \mixed $submitted the answer given by student
 * @return \var $result the correction display
 */
function domoscio_get_multi_choice_result($question, $submitted) {
    global $DB;
    $result = new stdClass;
    $i = $j = 0;

    $correctanswers = $DB->get_records('lesson_answers', array('pageid' => $question->id, 'grade' => '100'), '', '*');
    if (count($correctanswers) > 1) {
        $single = 0;
    } else {
        $single = 1;
    }

    $scale = 0.01;

    $answerslist = domoscio_get_answers($question->id);
    $rightanswer = domoscio_get_right_answers($question->id, $single);
    $rightanswer = array_shift($rightanswer);
    $length = count($rightanswer);
    $correctiondisplay = "";

    if ($length > 1) {
        foreach ($rightanswer as $correction) {
            $correctiondisplay .= strip_tags($correction->answer);
            $j++;
            if ($length > $j) {
                $correctiondisplay .= ", ";
            }
        }
    } else {
        $correctiondisplay = $rightanswer->answer;
    }

    $answers = array();
    $result->score = 0;
    foreach ($answerslist as $answer) {
        $class = null;

        if (isset($single)) {
            if ($single == 1) {
                if ($i == $submitted->{'q0:'.$question->id.'_answer'}) {
                    $checkradio = "checked";

                    if ($answer->answer !== $rightanswer->answer) {
                        $class = "incorrect";
                    } else {
                        $class = "correct";
                        $result->score = ($answer->fraction * $scale);
                    }
                } else {
                    $checkradio = null;
                }
                $qinput = html_writer::tag('input', '', array('disabled' => 'disabled',
                                                                    'id' => 'q0:'.$question->id.'_answer',
                                                                  'type' => 'radio',
                                                                 'value' => $i,
                                                                  'name' => 'q0:'.$question->id.'_answer ',
                                                               'checked' => $checkradio));
                $qlabel = html_writer::tag('label', strip_tags($answer->answer), array('for' => 'q0:'.$question->id.'_answer'));
                $answers[] = html_writer::tag('div', $qinput . $qlabel, array('class' => 'r0 '.$class));
            } else {
                if (isset($submitted->{'q0:'.$question->id.'_choice'.$i}) && $i == $submitted->{'q0:'.$question->id.'_choice'.$i}) {
                    $checkcheckbox = "checked";

                    if ($answer->fraction > 0) {
                        $class = 'correct';
                        $result->score += ($answer->fraction * $scale);
                    } else {
                        $class = "incorrect";
                    }
                } else {

                    $checkcheckbox = null;
                    if ($answer->fraction > 0) {
                        $class = "incorrect";
                    }
                }
                $qinput = html_writer::tag('input', '', array('disabled' => 'disabled',
                                                                    'id' => 'q0:'.$question->id.'_choice'.$i,
                                                                  'type' => 'checkbox',
                                                                 'value' => $i,
                                                                  'name' => 'q0:'.$question->id.'_choice'.$i,
                                                               'checked' => $checkcheckbox));
                $qlabel = html_writer::tag('label', strip_tags($answer->answer), array('for' => 'q0:'.$question->id.'_choice'.$i));
                $answers[] = html_writer::tag('div', $qinput . $qlabel, array('class' => 'r0 '.$class));
            }
        } else {
            if ($i == $submitted->{'q0:'.$question->id.'_answer'}) {
                $checkradio = "checked";

                if ($answer->answer !== $rightanswer->answer) {
                    $class = "incorrect";
                } else {
                    $class = "correct";
                    $result->score = $answer->fraction;
                }
            } else {
                $checkradio = null;
            }
            $qinput = html_writer::tag('input', '', array('disabled' => 'disabled',
                                                                'id' => 'q0:'.$question->id.'_answer',
                                                              'type' => 'radio',
                                                             'value' => $i,
                                                              'name' => 'q0:'.$question->id.'_answer ',
                                                           'checked' => $checkradio));
            $qlabel = html_writer::tag('label', strip_tags($answer->answer), array('for' => 'q0:'.$question->id.'_answer'));
            $answers[] = html_writer::tag('div', $qinput . $qlabel, array('class' => 'r0 '.$class));
        }

        $i++;
    }
    $answersfetched = implode("", $answers);

    $qablock = html_writer::tag('div', get_string('select_answer', 'domoscio'), array('class' => 'prompt')).
               html_writer::tag('div', $answersfetched, array('class' => 'answer'));
    $divanswer = html_writer::tag('div', get_string('correction', 'domoscio').$correctiondisplay, array('class' => 'rightanswer'));

    $output = html_writer::tag('div', '<p>'.$question->contents.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('div', $qablock, array('class' => 'ablock'));
    $output .= html_writer::tag('div', $divanswer, array('class' => 'outcome'));

    $result->score = round($result->score * 100);

    $result->output = $output;
    return $result;
}

/**
 * Displays correction for match question
 *
 * @param \stdClass $question the selected question data
 * @param \mixed $submitted the answer given by student
 * @return \var $result the correction display
 */
function domoscio_get_match_result($question, $submitted) {
    global $CFG, $DB;
    $result = new stdClass;
    $options = $lists = $subquestions = $table = array();

    $subquestions = $DB->get_records_sql("SELECT *
                                            FROM {lesson_answers}
                                           WHERE `pageid` = :pid
                                             AND `response` IS NOT NULL",
                                         array('pid' => $question->id)
                                        );

    foreach ($subquestions as $subquestion) {
        $subquestion->answertext = $subquestion->response;
        $subquestion->questiontext = $subquestion->answer;
    }

    $i = 1;
    $subresult = 0;

    foreach ($subquestions as $subquestion) {
        $class = null;
        if (($submitted->{'q0:'.$question->id.'_sub'.$i}) == $subquestion->answertext) {
            $class = "correct";
            $subresult += 1;
        } else {
            $class = "incorrect";
            $subresult += 0;
        }

        $tdselect = html_writer::tag('select',
                                     '<option>'.$submitted->{'q0:'.$question->id.'_sub'.$i}.'</option>',
                                     array('id' => 'menuq0:'.$question->id.'_sub'.$i,
                                        'class' => 'select '.$class.' menuq0:'.$question->id.'_sub'.$i,
                                         'name' => 'q0:'.$question->id.'_sub'.$i,
                                     'disabled' => 'disabled'));
        $tdcontrol = html_writer::tag('label', 'Answer :', array('for' => 'q0:'.$question->id.'_sub'.$i.'_answer',
                                                               'class' => 'subq accesshide')) . $tdselect;
        $rowcontent = html_writer::tag('td', '<p>'.$subquestion->questiontext.'</p>', array('class' => 'text')).
                      html_writer::tag('td', $tdcontrol, array('class' => 'control'));

        $table[] = html_writer::tag('tr', $rowcontent, array('class' => 'r0'));

        $i++;
    }

    $tablefetched = implode("", $table);

    $output = html_writer::tag('div', '<p>'.$question->contents.'</p>', array('class' => 'qtext'));
    $output .= html_writer::tag('table', '<tbody>'.$tablefetched.'</tbody>', array('class' => 'answer'));

    $result->output = $output;

    if (count($subquestions) != $subresult) {
        $result->score = 0;
    } else {
        $result->score = 100;
    }

    return $result;
}

/**
 * Add or update events with next due dates in user calendar
 *
 * @param \stdClass $domoscio the Domoscio plugin instance data
 * @param \stdClass $knstudent the knowledge node student data
 * @param \integer $kn the knowledge node id
 * @param \stdClass $resource the linked ressource module data
 * @return void
 */
function domoscio_create_event($domoscio, $knstudent, $kn, $resource) {
    global $DB, $CFG, $USER;

    $event = new stdClass;
    $event->name    = $resource->name." : ".get_course($domoscio->course)->fullname;
    $event->description = get_string('gottatest', 'domoscio');
    $event->courseid    = 0;
    $event->groupid     = 0;
    $event->userid      = $USER->id;
    $event->modulename  = 'domoscio';
    $event->instance    = $domoscio->id;
    $event->eventtype   = 'user';
    $event->timestart   = strtotime($knstudent->next_review_at);
    $event->uuid        = $knstudent->id;
    $event->visible     = instance_is_visible('domoscio', $domoscio);
    $event->timeduration    = 60;

    $check = $DB->get_record('event', array('instance' => $domoscio->id, 'userid' => $USER->id, 'uuid' => $knstudent->id), '*');

    if (!empty($check)) {
        $reminder = calendar_event::load($check->id);
        $reminder->update($event);
    } else {
        calendar_event::create($event);
    }
}

/**
 * Compute various statistics to display to student and course creator
 *
 * @param \int $kn the knowledge node displayed
 * @param \int $limitfrom the sql query offset for pagination
 * @param \int $limitnum the sql query limit for pagination
 * @return \stdClass $stats all stats calculated
 */
function domoscio_get_stats($kn, $limitfrom = null, $limitnum = null) {
    global $DB;
    $config = get_config('domoscio');
    $rest = new mod_domoscio_client();
    $stats = new stdClass();

    $knstudents = $DB->get_records('domoscio_knode_students', array('knodeid' => $kn), 'user ASC', '*', $limitfrom, $limitnum);
    $stats->count_students = count($knstudents);

    $history = $enrolledstudents = array();
    $attempts = $rightattempts = $todotests = 0;

    foreach ($knstudents as $kns) {
        $apicall = json_decode($rest->seturl($config, 'knowledge_node_students', $kns->knodestudentid)->get());
        $history[] = $apicall->history;

        if (strtotime($apicall->next_review_at) < time()) {
            $todotests++;
        }

        $enrolledstudents[] = $apicall;
    }

    foreach ($history as $studenthistory) {
        $attempts += count(str_split($studenthistory));
        $rightattempts += count(array_filter(str_split($studenthistory)));
    }

    if ($attempts > 0) {
        $stats->global_success = round(($rightattempts / $attempts) * 100, 2);
        $stats->attempts = $attempts;
        $stats->todo = $todotests;
        $stats->enrolled = $enrolledstudents;

        return $stats;
    }
}

/**
 * Retrive student by one of his knowledge node students id
 *
 * @param \int $kns knowledge node student id
 * @return \stdClass $student the student datas
 */
function domoscio_get_student_by_kns($kns) {
    global $DB, $CFG;
    $config = get_config('domoscio');

    $student = $DB->get_record_sql("SELECT *
                                      FROM {user}
                                INNER JOIN {domoscio_knode_students}
                                        ON {domoscio_knode_students}.`user` = {user}.`id`
                                     WHERE {domoscio_knode_students}.`knodestudentid` = :knsid",
                                   array('knsid' => $kns)
                                  );

    return $student;
}

/**
 * A simple function that add 's' to plural nouns
 *
 * @param \array $count the array embeding data to count
 * @return \string $domoscioplural leave empty or fill with a 's'
 */
function domoscio_plural($count) {
    if (count($count) > 1) {
        return $domoscioplural = "s";
    } else {
        return $domoscioplural = "";
    }
}

/**
 * Convert UNIX timestamp to human readable time
 *
 * @param \int $seconds the time we want to convert
 * @return \string the time converted
 */
function domoscio_sec_to_time($seconds) {
    $dtf = new DateTime("@0");
    $dtt = new DateTime("@$seconds");
    if ($seconds <= (strtotime("tomorrow midnight") - strtotime("now")) && $seconds > 0) {
        return get_string('today', 'domoscio');
    } else if ($seconds > (strtotime("tomorrow midnight") - strtotime("now")) && $seconds <= (strtotime("+2 days midnight") - strtotime("now"))) {
        return get_string('tomorrow', 'domoscio');
    } else if ($seconds <= 0) {
        return get_string('text3', 'domoscio');
    } else {
        return $dtf->diff($dtt)->format('%a '.get_string('days', 'domoscio'));
    }
}
