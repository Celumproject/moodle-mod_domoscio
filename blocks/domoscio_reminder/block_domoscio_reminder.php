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
require_once('mod/domoscio/lib.php');
require_once('mod/domoscio/sdk/client.php');

class block_domoscio_reminder extends block_base {


    public function init() {
        $this->title = get_string('domoscioreminder', 'block_domoscio_reminder');
    }

    public function get_content() {
        global $DB, $USER, $CFG;
        $config = get_config('domoscio');

        if ($this->content !== null) {
          return $this->content;
        }

        $count = count_tests($config);

        if(!empty($count)){$this->content->footer = "<a href=".$CFG->wwwroot."/mod/domoscio/doquiz.php>Let's go !</a>";}

        $this->content         =  new stdClass;
        $this->content->text   = 'You have '.count($count).' tests to pass';


        return $this->content;
    }

}   // Here's the closing bracket for the class definition
