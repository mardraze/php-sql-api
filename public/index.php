<?php

require __DIR__.'/../vendor/autoload.php';

if(!file_exists(__DIR__.'/../config.inc.php')){
    $config = file_get_contents(__DIR__.'/../config.sample.inc.php');
    $config = str_replace("cfg['blowfish_secret'] = '';", "cfg['blowfish_secret'] = '".md5('mardraze'.uniqid().rand(0,100000).'')."';", $config);
    file_put_contents(__DIR__.'/../config.inc.php', $config);
    unset($config);
}
$file = __DIR__.'/../config.inc.php';
if(!file_exists($file)){
    $file = __DIR__.'/../config.sample.inc.php';
}

$cfg = [];
$cfg['Servers'] = [];

require $file;

$cfg['http'] = true;

$api = new \Mardraze\SqlApi\Api($cfg);
unset($cfg);

$api->processInput();
