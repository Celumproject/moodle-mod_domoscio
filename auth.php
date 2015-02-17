<?php

defined('MOODLE_INTERNAL') || die;


if (!function_exists("curl_init") || !function_exists("curl_setopt") || !function_exists("curl_exec") || !function_exists("curl_close")) {
    echo json_encode(array('success' => false, 'error' => get_string('proxy_curl_missing', 'easycastms')));
    die;
}







?>