<?php

/*
 * catprocess config
 * @by xiaoliang
 */
return $config = [
    'moduleName'      => 'member_module', //模块分组名称

    //日志
    'logPath'=> __DIR__ . '/log',//log目录
    'logSaveFileApp' => 'application.log', //默认log存储名字
    'logSaveFileWorker'=> 'workers.log', // 进程启动相关log存储名字
    'pidPath' => __DIR__ . '/log',

    //进程相关
    'processName' => ':member', // 设置进程名, 方便管理, 默认值 swooleTopicQueue
    'sleepTime' => 40000, // 防止进程CPU使用过高单位:MS，这里是一个保护措施

    //业务相关
    'workerDir' => __DIR__ . "/task", // task工作目录，用来存放业务代码
    'workerLoadFileBefore' => [
        'framework.php'
    ], // 执行任务钱，需要加载的外部框架文件，与 workerDir关联，例：__DIR__ . "/task" . "/framework.php"


    'redis'            => [
        'host'  => '127.0.0.1',
        'port'  => '6379',
        'preKey'=> 'task1-',
        //'password'=>'',
    ],

    //exec任务相关,name的名字不能相同
    'exec'      => [
        [
            'name' => 'member_1',// 名称即使文件名，请慎用
            'max_request' => 1000,
            'memory_limit' => 1024, // 单位:MB 最大内存限制，超出将自动销毁重新启动
            'workNum'   => 10
        ],
        /*
        [
            // 名称即使文件名，请慎用
            'name'      => 'member_2',
            'workNum'   => 2
        ],
        */
    ],
];
