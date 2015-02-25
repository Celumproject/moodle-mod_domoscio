<?php


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');



$config=get_config('domoscio');



$ch = curl_init();

//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');   
curl_setopt($ch, CURLOPT_URL, "http://stats-engine.domoscio.com/v1/companies/".$config->domoscio_id."?token=".$config->domoscio_apikey  );
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_USERPWD, "mohsan:b326f68391fe36101cf48feee48471f5");

$ch_reponse=curl_exec($ch);


$reponse=json_decode($ch_reponse);

$status = curl_getinfo($ch, CURLINFO_HTTP_CODE); 

curl_close($ch); 

var_dump($reponse);






?>