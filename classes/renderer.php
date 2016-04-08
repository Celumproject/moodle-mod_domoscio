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
 * Defines the renderer for the quiz module.
 *
 * @package   mod_domoscio
 * @copyright 2016 Domoscio SA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../lib.php');

defined('MOODLE_INTERNAL') || die();


/**
 * The renderer for test sessions.
 *
 * @copyright 2016 Domoscio SA
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_domoscio_renderer extends plugin_renderer_base {
    /**
     * Outputs the navigation block panel
     *
     * @param $session session data
     */
    public function navigation_panel($testsession, $page, $cmid, $end) {
        global $CFG, $OUTPUT, $PAGE;

        $output = '';
        $testurl = new moodle_url("$CFG->wwwroot/mod/domoscio/doquiz.php");
        $urlresultsend = new moodle_url("$CFG->wwwroot/mod/domoscio/results.php");
        $urlresultsend->param('sesskey', sesskey());
        $urlresultsend->param('id', $cmid);
        $urlresultsend->param('end', true);

        $i = 0;
        if ($testsession->get_list()[0]->get_test() != null) {
            foreach ($testsession->get_list() as $test) {
                if ($test->get_test()) {
                    $testurl->param('kn', $test->get_related_kn());
                    $stateicon = "";

                    if ($result = $test->get_test()->get_result()) {
                        if ($result->score == 100) {
                            $class = "success";
                            $feedbackclass = "correct";
                        } else {
                            $class = "error";
                            $feedbackclass = "incorrect";
                        }

                        $attributes = array(
                            'src' => $OUTPUT->pix_url('i/grade_' . $feedbackclass),
                            'alt' => get_string($feedbackclass, 'question'),
                            'class' => 'questioncorrectnessicon',
                        );

                        $stateicon = html_writer::empty_tag('img', $attributes);
                    }

                    if ($test->get_related_kn() == $page) {
                        $output .= "<b>";
                    }
                    if ((array_key_exists($i, $testsession->get_todo()) && $end == false) && ($PAGE->url->get_param('kn') != $test->get_related_kn())) {
                        $output .= html_writer::link($testurl, $test->get_kn_data()->display." - ".$test->get_item()->name);
                    } else {
                        $output .= html_writer::tag("span", $test->get_kn_data()->display." - ".$test->get_item()->name, array('class' => "text-muted"));
                    }
                    if ($test->get_related_kn() == $page) {
                        $output .= "</b>";
                    }
                    if ($stateicon != "") {
                        $output .= " ".$stateicon;
                    }
                    $output .= "<br/>";
                    $i++;
                }
            }
        } else {
            $urlresultsend = new moodle_url("$CFG->wwwroot/mod/domoscio/view.php");
            $urlresultsend->param('id', $cmid);
        }

        $output .= "<hr/>";
        if ($end == false) {
            $output .= $OUTPUT->single_button($urlresultsend, get_string('end_btn', 'domoscio'));
        }

        return $output;
    }
}
