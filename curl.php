<?php 
error_reporting(0);
class cURL {
    function getheader($url) {
        $process = curl_init($url);
        curl_setopt($process, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); 
        curl_setopt($process, CURLOPT_HEADER, 1);
        curl_setopt($process, CURLOPT_TIMEOUT, 60);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($process, CURLOPT_NOBODY, 1);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($process,CURLOPT_CAINFO, NULL);
        curl_setopt($process,CURLOPT_CAPATH, NULL);
        $return = curl_exec($process);
        curl_close($process);
        return $return;
    }

    function get($url) {
        $process = curl_init($url); 
        curl_setopt($process, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); 
        curl_setopt($process, CURLOPT_TIMEOUT, 60); 
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, 2);
        $return = curl_exec($process);
        curl_close($process);
        return $return;
    }

    function post($url, $data) {
        $process = curl_init($url);
        curl_setopt($process, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); 
        curl_setopt($process, CURLOPT_TIMEOUT, 60);
        curl_setopt($process, CURLOPT_POST, TRUE);
        curl_setopt($process, CURLOPT_POSTFIELDS, $data);
        curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST, 2);
        $return = curl_exec($process);
        curl_close($process);
        return $return;
    }

    function checkCode($url) {
        $process = curl_init();
        curl_setopt($process, CURLOPT_URL, $url);
        curl_setopt($process, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36");
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($process, CURLOPT_SSL_VERIFYHOST,false);
        curl_setopt($process, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($process, CURLOPT_MAXREDIRS, 10);
        curl_setopt($process, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($process, CURLOPT_TIMEOUT, 20);
        $rt = curl_exec($process);
        $info = curl_getinfo($process);
        return $info["http_code"];
    }
	
}
?>