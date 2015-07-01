<?php

$config=get_config('domoscio');



$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "$config->domoscio_apiurl/companies/".$config->domoscio_id."?token=".$config->domoscio_apikey  );
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

curl_setopt($ch, CURLOPT_HEADER, true);

$ch_reponse=curl_exec($ch);


$reponse=json_decode($ch_reponse);

$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

var_dump($reponse);






?>
