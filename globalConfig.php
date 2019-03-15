<?php

/*
 * 全局静态配置
 * @by xiaoliang
 */
define('GLOBAL_PATH', str_replace('\\', '/', dirname(__FILE__)) . '/');

return $config = [

    //日志
    'logPath'=> GLOBAL_PATH . '/log',//log目录
    'logSaveFileApp' => 'application.log', //默认log存储名字
    'logSaveFileWorker'=> 'workers.log', // 进程启动相关log存储名字
    'pidPath' => GLOBAL_PATH . '/log',

    // 进程相关
    'sleepTime' => 40000, // 防止进程CPU使用过高单位:MS，这里是一个保护措施

    //业务相关
    'workerDir' => GLOBAL_PATH . "/../task", // task工作目录，用来存放业务代码
    'workerLoadFileBefore' => [
        'framework.php'
    ], // 执行任务钱，需要加载的外部框架文件，与 workerDir关联，例：__DIR__ . "/task" . "/framework.php"
];
