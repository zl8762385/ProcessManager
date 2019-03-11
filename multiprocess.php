#!/usr/bin/env php
<?php
/*
 * 入口脚本
 * 使用方法：php multiprocess -s stop -c member.php
 * */

define('PROCESS_PATH', __DIR__);
date_default_timezone_set('Asia/Shanghai');

require PROCESS_PATH . '/vendor/autoload.php';
$globalConfig = require_once PROCESS_PATH . '/globalConfig.php';

$param = getopt('s:c:');
$opt =$param['s'] ?? '';
$configFile =$param['c'] ?? '';

// 业务工作目录
$workConfigDir = $globalConfig['workerDir'];

if ( !file_exists( $workConfigDir ) ) {
    die('目录不存在');
} else {
    $configFile = $workConfigDir . '/' . $configFile;
}

if ($configFile && file_exists($configFile)) {
    $config = require_once $configFile;
} else {
    die('找不到配置文件.');
}

// 静态配饰和业务配置合并
$config = array_merge( $globalConfig, $config );

$console = new Kcloze\MultiProcess\Console($opt, $config);
$console->run();
