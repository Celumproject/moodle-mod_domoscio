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
* @uses global $OUTPUT to produce notices and other messages
*/

class block_domoscio_reminder extends block_base {


    public function init() {
      global $OUTPUT;
        $icon = html_writer::tag('img', '', array('src'=>$OUTPUT->pix_url('icon','domoscio','domoscio',array('class'=>'icon')), 'class'=>'activityicon', 'alt'=>'disable'));
        $this->title = $icon.' '.get_string('domoscioreminder', 'block_domoscio_reminder');
    }

    public function get_content() {
        global $DB, $USER, $CFG;

        if ($this->content !== null) {
          return $this->content;
        }

        if (user_has_role_assignment($USER->id,5)) {
            $config = get_config('domoscio');

            $count = $this->count_tests($config);

            if(count($count) > 1){$plural = "s";}else{$plural = "";}

            $this->content         = new stdClass;
            $this->content->text   = '<span class="badge badge-important" style="font-size:18px">'.count($count).'</span>'.get_string('text2', 'block_domoscio_reminder').$plural.get_string('text3', 'block_domoscio_reminder');
            if(!empty($count)){$this->content->footer = "<a href=".$CFG->wwwroot."/mod/domoscio/index.php>Let's go !</a>";}
        }
        return $this->content;

    }

    public function count_tests($config)
    {
        global $DB, $USER, $CFG;

        $kn_students = $DB->get_records_sql("SELECT *
                                               FROM ".$CFG->prefix."knowledge_node_students
                                         INNER JOIN ".$CFG->prefix."knowledge_nodes
                                                 ON ".$CFG->prefix."knowledge_nodes.`knowledge_node_id` = ".$CFG->prefix."knowledge_node_students.`knowledge_node_id`
                                              WHERE ".$CFG->prefix."knowledge_node_students.`user` = $USER->id
                                                AND (".$CFG->prefix."knowledge_nodes.`active` IS NULL
                                                 OR ".$CFG->prefix."knowledge_nodes.`active` = '1')");
        $i = 0;
        $list = array();
        foreach($kn_students as $kn_student)
        {
            $result = json_decode($this->setUrl($config, 'knowledge_node_students', $kn_student->kn_student_id)->get());

            if(strtotime($result->next_review_at) < time() && $result->next_review_at != null && $result->active == 'true')
            {
                $list[] = $kn_student->kn_student_id;
            }
        }

        return $list;
    }

    private $_url;
    public function setUrl($config, $feature, $var)
    {
        $this->_url = $config->domoscio_apiurl."/companies/".$config->domoscio_id."/".$feature."/".$var."?token=".$config->domoscio_apikey;
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

}   // Here's the closing bracket for the class definition
