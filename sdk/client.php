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
 * Domoscio API SDK
 *
 * The Domoscio API SDK, contains all needed functions to interact with
 * Domoscio REST API
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


 /**
  * The client class which allow to interact with Domoscio API
  *
  * @package    mod_domoscio
  * @copyright  2015 Domoscio
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class mod_domoscio_client
{
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
    public function seturl($config, $feature, $var, $optvar = null, $optvar2 = null) {
        $this->_url = $config->domoscio_apiurl."/instances/".$config->domoscio_id."/".$feature."/".$var."/".$optvar."?token=".$config->domoscio_apikey.$optvar2;
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
     * Defining method to send datas to API
     *
     * @param \string $postparams the json sent
     * @param \array $getparams the url
     * @return method launch
     */
    public function post($postparams=array(), $getparams = array()) {
        return $this->_launch($this->_makeurl($getparams),
                            $this->_createcontext('POST', $postparams));
    }

    /**
     * Defining method to edit datas on API
     *
     * @param \string $pcontent the json sent
     * @param \array $getparams the url
     * @return method launch
     */
    public function put($pcontent = null, $getparams = array()) {
        return $this->_launch($this->_makeurl($getparams),
                            $this->_createcontext('PUT', $pcontent));
    }

    /**
     * Defining method to delete datas on API
     *
     * @param \string $pcontent the json sent
     * @param \array $getparams the url
     * @return method launch
     */
    public function delete($pcontent = null, $getparams = array()) {
        return $this->_launch($this->_makeurl($getparams),
                            $this->_createcontext('DELETE', $pcontent));
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
