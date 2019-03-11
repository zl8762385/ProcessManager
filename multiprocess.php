<?php

/*
 * 入口脚本
 * 使用方法：php multiprocess -s stop -c member.php
 * */

define('APP_PATH', __DIR__);
date_default_timezone_set('Asia/Shanghai');

require APP_PATH . '/vendor/autoload.php';

$param                  = getopt('s:c::');
$opt                    =$param['s'] ?? '';
$configFile             =$param['c'] ?? APP_PATH . '/config.php';
if ($configFile && file_exists($configFile)) {
    $config = require_once $configFile;
} else {
    die('config file can not find!');
}

$console = new Kcloze\MultiProcess\Console($opt, $config);
$console->run();
