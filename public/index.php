<?php

require __DIR__.'/../vendor/autoload.php';

if(!file_exists(__DIR__.'/../config.inc.php')){
    $config = file_get_contents(__DIR__.'/../config.sample.inc.php');
    $config = str_replace("define('SECURE_TOKEN', '');", "define('SECURE_TOKEN', '".md5('mardraze'.uniqid().rand(0,100000).'')."');", $config);
    file_put_contents(__DIR__.'/../config.inc.php', $config);
    unset($config);
}

require __DIR__.'/../config.inc.php';

$api = new \Mardraze\SqlApi\Api();
$api->processInput();
