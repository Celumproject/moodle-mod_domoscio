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
* Module instance settings form
*
* @package    mod_domoscio
* @copyright  2015 Domoscio
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
class mod_domoscio_select_notion_form extends moodleform {

    public function definition() {
        global $DB, $CFG;

        $config = get_config('domoscio');
        $mform = $this->_form;

        $notions = $DB->get_records('knowledge_nodes', array('instance' => $this->_customdata['instance']), '', '*');

        //fill the checkbox if already selected
        $selected_notions = $DB->get_records_sql("SELECT *
                                                    FROM {knowledge_nodes}
                                                   WHERE `instance` = ".$this->_customdata['instance']."
                                                     AND `active` = '1'");

        $selected = array();

        foreach($selected_notions as $selected_notion)
        {
            $selected[] = $selected_notion->id;
        }

        $mform = $this->_form;

        foreach($notions as $notion)
        {
            $title = json_decode($this->setUrl("$config->domoscio_apiurl/companies/$config->domoscio_id/knowledge_nodes/$notion->knowledge_node_id?token=$config->domoscio_apikey")->get());

            if(in_array($notion->id, $selected)){$check = true;}else{$check = false;}

            if(intval($notion->knowledge_node_id) == intval($this->_customdata['parent']))
            {
                $mform->addElement('html', '<blockquote class="muted"><small>'.get_string('whole_expl', 'domoscio').'</small></blockquote>');
                $notion_name = html_writer::tag('span', get_string('global_module', 'domoscio'), array('class' => 'alert alert-info'));
                $parent_id = $notion->id;
                $parent = $mform->addElement('advcheckbox', $notion->id, '', $notion_name, array('group' => 1, 'class' => 'parent_notion'), array(0, 1))->setChecked($check);

                $mform->addElement('html', '<blockquote class="muted"><small>'.get_string('each_expl', 'domoscio').'</small></blockquote>');
            }
            else
            {
                $notion_name = $title->name;
                $mform->addElement('advcheckbox', $notion->id, '', $notion_name, array('group' => 1, 'class' => 'children_notions'), array(0, 1))->setChecked($check);
            }

        }

        $this->add_action_buttons();
    }

    private $_url;
    public function setUrl($url)
    {
        $this->_url = $url;
        return $this;
    }

    public function get($params = array())
    {
        return $this->_launch($this->_makeUrl($params),
                            $this->_createContext('GET'));
    }

    protected function _createContext($pMethod, $pContent = null)
    {
        $opts = array(
              'http'=>array(
                            'method'=>$pMethod,
                            'header'=>'Content-type: application/json',
                          )
        );
        if ($pContent !== null){
            if (is_array($pContent)){
                $pContent = http_build_query($pContent);
            }
            $opts['http']['content'] = $pContent;
        }

        return stream_context_create($opts);
    }

    protected function _makeUrl($pParams)
    {
        return $this->_url
            .(strpos($this->_url, '?') ? '' : '?')
            .http_build_query($pParams);
    }

    protected function _launch ($pUrl, $context)
    {
        if (($stream = fopen($pUrl, 'r', false, $context)) !== false)
        {
            $content = stream_get_contents($stream);
            $header = stream_get_meta_data($stream);
            fclose($stream);
            //return array('content'=>$content, 'header'=>$header);
            return $content;
        }
        else
        {
            return false;
        }
    }
}
