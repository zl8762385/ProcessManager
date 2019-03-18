<?php
/*
 * 全局静态配置
 * @author xiaoliang
 * 您可以自由使用该源码，但是在使用过程中，请保留作者信息。尊重他人劳动成果就是尊重自己
 * */
define('GLOBAL_PATH', str_replace('\\', '/', dirname(__FILE__)) . '/');

// 业务代码存放目录
$workerName = "../task";

return $config = [

    //日志
    'logPath'=> GLOBAL_PATH . "/$workerName/log",//log目录
    'logSaveFileApp' => 'application.log', //默认log存储名字
    'logSaveFileWorker'=> 'workers.log', // 进程启动相关log存储名字
    'pidPath' => GLOBAL_PATH . "/$workerName/log",

    // 进程相关
    'sleepTime' => 40000, // 防止进程CPU使用过高单位:MS，这里是一个保护措施

    //业务相关
    'workerDir' => GLOBAL_PATH . "/$workerName", // task工作目录，用来存放业务代码
    'workerLoadFileBefore' => [
        'framework.php'
    ], // 执行任务钱，需要加载的外部框架文件，与 workerDir关联，例：__DIR__ . "/task" . "/framework.php"
];