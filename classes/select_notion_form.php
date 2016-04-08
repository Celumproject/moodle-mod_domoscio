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
 * The class defining the form displayed on the select notion view
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * Select notion form
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_domoscio_select_notion_form extends moodleform {
    /**
     * The form
     *
     * @return void
     */
    public function definition() {
        global $DB, $CFG, $OUTPUT;

        $config = get_config('domoscio');
        $strdelete = get_string('delete');
        $mform = $this->_form;

        $notions = $DB->get_records('domoscio_knowledge_nodes', array('instance' => $this->_customdata['instance']), '', '*');

        // Fill the checkbox if already selected
        $selectednotions = $DB->get_records_sql("SELECT *
                                                   FROM {domoscio_knowledge_nodes}
                                                  WHERE instance = :instance
                                                    AND active = '1'",
                                                array('instance' => $this->_customdata['instance'])
                                               );

        $selected = array();

        foreach ($selectednotions as $selectednotion) {
            $selected[] = $selectednotion->id;
        }

        $mform = $this->_form;

        foreach ($notions as $notion) {
            $title = json_decode($this->seturl($config, "knowledge_nodes", $notion->knodeid)->get());
            $deleteurl = new moodle_url('delete_notion.php');
            $deleteurl->params(array('kn' => $notion->knodeid, 'sesskey' => sesskey()));

            $deletelink = html_writer::link(new moodle_url($deleteurl, array('kn' => $notion->knodeid, 'sesskey' => sesskey())), html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/delete'), 'alt'=>$strdelete, 'class'=>'iconsmall')), array('title'=>$strdelete));

            if (in_array($notion->id, $selected)) {
                $check = true;
            } else {
                $check = false;
            }

            if (intval($notion->knodeid) == intval($this->_customdata['parent'])) {
                $mform->addElement('html', '<blockquote class="muted"><small>'.get_string('whole_expl', 'domoscio').'</small></blockquote>');
                $notionname = html_writer::tag('span', get_string('global_module', 'domoscio'), array('class' => 'alert alert-info'));
                $parentid = $notion->id;
                $parent = $mform->addElement('advcheckbox',
                                             $notion->id,
                                             '',
                                             $notionname,
                                             array('group' => 1, 'id' => 'parent_notion'),
                                             array(0, 1))->setChecked($check);

                $mform->addElement('html',
                                   '<blockquote class="muted"><small>'.get_string('each_expl', 'domoscio').'</small></blockquote>');
            } else {
                $notionname = $title->name;
                $mform->addElement('advcheckbox',
                                   $notion->id,
                                   '',
                                   $notionname." ".$deletelink,
                                   array('group' => 1, 'class' => 'children_notions'), array(0, 1))->setChecked($check);
            }

        }

        $this->add_action_buttons();
    }

    /**
     * The Domoscio API url
     *
     * @var the url
     */
    private $_url;

    /**
     * Building the url to send to Domoscio API
     *
     * @param \stdClass $config
     * @param \string $feature
     * @param \int $var
     * @return url
     */
    public function seturl($config, $feature, $var) {
        $this->_url = $config->domoscio_apiurl."/instances/".$config->domoscio_id."/".$feature."/".$var."?token=".$config->domoscio_apikey;
        return $this;
    }

    /**
     * Defining method to get datas from API
     *
     * @param \array $params
     * @return method launch
     */
    public function get($params = array()) {
        return $this->_launch($this->_makeurl($params),
                            $this->_createcontext('GET'));
    }

    /**
     * Defining context
     *
     * @param \string $pmethod
     * @param \void $pcontent
     * @return method
     */
    protected function _createcontext($pmethod, $pcontent = null) {
        $opts = array(
              'http' => array(
                            'method' => $pmethod,
                            'header' => 'Content-type: application/json',
                          )
        );
        if ($pcontent !== null) {
            if (is_array($pcontent)) {
                $pcontent = http_build_query($pcontent);
            }
            $opts['http']['content'] = $pcontent;
        }

        return stream_context_create($opts);
    }

    /**
     * Defining query to API
     *
     * @param \array $pparams
     * @return method
     */
    protected function _makeurl($pparams) {
        return $this->_url
            .(strpos($this->_url, '?') ? '' : '?')
            .http_build_query($pparams);
    }

    /**
     * Sending request to API
     *
     * @param \string $purl
     * @param \string $context
     * @return \array $content
     */
    protected function _launch ($purl, $context) {
        if (($stream = fopen($purl, 'r', false, $context)) !== false) {
            $content = stream_get_contents($stream);
            $header = stream_get_meta_data($stream);
            fclose($stream);

            return $content;
        } else {
            return false;
        }
    }
}
