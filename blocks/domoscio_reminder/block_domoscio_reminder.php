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
* Domoscio reminder block
*
* @copyright  Domoscio
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

include 'mod/domoscio/sdk/client.php';

class block_domoscio_reminder extends block_base {


    public function init() {
        $this->title = get_string('domoscioreminder', 'block_domoscio_reminder');
    }

    public function get_content() {
        global $DB, $USER, $CFG;

        if ($this->content !== null) {
          return $this->content;
        }

        if (user_has_role_assignment($USER->id,5)) {
            $config = get_config('domoscio');

            $count = $this->count_tests($config);

            if(!empty($count)){$this->content->footer = "<a href=".$CFG->wwwroot."/mod/domoscio/doquiz.php>Let's go !</a>";}

            $this->content         =  new stdClass;
            $this->content->text   = 'You have '.count($count).' tests to pass';

        }
        return $this->content;

    }

    public function count_tests($config)
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

}   // Here's the closing bracket for the class definition
