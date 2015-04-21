<?php

/**
 * The Domoscio API SDK, contains all needed functions to interact with
 * Domoscio REST API
 *
 * @package    mod_domoscio
 * @copyright  2015 Domoscio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class domoscio_client
{
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

    public function post($post_params=array(), $get_params = array())
    {
        return $this->_launch($this->_makeUrl($get_params),
                            $this->_createContext('POST', $post_params));
    }

    public function put($pContent = null, $get_params = array())
    {
        return $this->_launch($this->_makeUrl($get_params),
                            $this->_createContext('PUT', $pContent));
    }

    public function delete($pContent = null, $get_params = array())
    {
        return $this->_launch($this->_makeUrl($get_params),
                            $this->_createContext('DELETE', $pContent));
    }

    protected function _createContext($pMethod, $pContent = null)
    {
        $opts = array(
              'http'=>array(
                            'method'=>$pMethod,
                            'header'=>'Content-type: application/x-www-form-urlencoded',
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

/* EXEMPLES D'APPELS AUX FONCTIONS CI DESSUS

$rest = new domoscio_client();

//lecture d'un livre
$livre = $rest->setUrl('http://bibliotheque/livre/1')->get();

//ecriture d'un livre
$rest->setUrl('http://bibliotheque/livre')->post($unLivre);

//modification d'un livre
$rest->setUrl('http://bibliotheque/livre/1')->put($unLivre);

//supression d'un livre
$rest->setUrl('http://bibliotheque/livre/1')->delete();

*/
