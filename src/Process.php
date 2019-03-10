<?php

/*
 * 进程管理核心文件 原框架中思想已完全改变，请不要拿原来框架覆盖本文件
 * @by xiaoliang
 * */

namespace Kcloze\MultiProcess;

class Process
{
    const STATUS_START               ='start'; //主进程启动中状态
    const STATUS_RUNNING             ='runnning'; //主进程正常running状态
    const STATUS_WAIT                ='wait'; //主进程wait状态
    const STATUS_STOP                ='stop'; //主进程stop状态
    const STATUS_RECOVER             ='recover'; //主进程recover状态
    const MASTER_KEY                 ='Status'; //主进程recover状态
    const WORKER_STATUS_KEY          ='Status-'; //主进程recover状态
    const REDIS_WORKER_MEMBER_KEY    ='Members-'; //主进程recover状态

    const PID_INFO_FILE = 'master.info'; //pid 序列化信息

    public $processName    = ':swooleMultiProcess'; // 进程重命名, 方便 shell 脚本管理
    private $workers;
    private $workersByPidName;
    private $ppid;
    private $configWorkersByNameNum;
    private $checkTickTimer       = 5000; //检查服务是否正常定时器,单位ms
    private $sleepTime            = 40000; //防止进程CPU使用过高单位:MS，这里是一个保护措施
    private $config               = [];
    private $pidFile              = 'master.pid'; // pid numbers
    public  $workerStatusFile     = 'workerStatus.info'; //worker状态
    private $status               =''; //主进程状态
    private $timer                =''; //定时器id
    private $redis                =null; //redis连接
    private $logSaveFileWorker    = 'workers.log';

    public function __construct()
    {
        $this->config  =  Config::getConfig();

        if (Config::hasRepeatingName($this->config['exec'], 'name')) {
            die('exec name has repeating name,fetal error!');
        }
        $this->logger = new Logs(Config::getConfig()['logPath'] ?? '', $this->config['logSaveFileApp'] ?? '');

        if (isset($this->config['pidPath']) && !empty($this->config['pidPath'])) {
            $mName = $this->config['moduleName'] ?? '' ;
            Utils::mkdir($this->config['pidPath']);
            $this->pidFile    =$this->config['pidPath'] . '/' . $mName .'_'. $this->pidFile;
            $this->pidInfoFile =$this->config['pidPath'] . '/' . $mName .'_'. self::PID_INFO_FILE;
            $this->workerStatusFile =$this->config['pidPath'] . '/' . $mName .'_'. $this->workerStatusFile;
        } else {
            die('config pidPath must be set!');
        }
        if (isset($this->config['processName']) && !empty($this->config['processName'])) {
            $this->processName = $this->config['processName'];
        }
        if (isset($this->config['sleepTime']) && !empty($this->config['sleepTime'])) {
            $this->sleepTime = $this->config['sleepTime'];
        }
        if (isset($this->config['logSaveFileWorker']) && !empty($this->config['logSaveFileWorker'])) {
            $this->logSaveFileWorker = $this->config['logSaveFileWorker'];
        }

        /*
         * master.pid 文件记录 master 进程 pid, 方便之后进程管理
         * 请管理好此文件位置, 使用 systemd 管理进程时会用到此文件
         * 判断文件是否存在，并判断进程是否在运行
         */

        if (file_exists($this->pidFile)) {
            $pid=$this->getMasterPid();
            if ($pid && @\Swoole\Process::kill($pid, 0)) {
                die('已有进程运行中,请先结束或重启' . PHP_EOL);
            }
        }

        \Swoole\Process::daemon();
        $this->ppid    = getmypid();
        $this->saveMasterPid();
        $this->setProcessName('process master ' . $this->ppid . $this->processName);
    }

    public function start()
    {
        $this->saveMasterData([self::MASTER_KEY =>self::STATUS_START]);
        if (!isset($this->config['exec'])) {
            die('config exec must be not null!');
        }
        $this->logger->log('process start pid: ' . $this->ppid, 'info', $this->logSaveFileWorker);

        $this->configWorkersByNameNum=[];
        foreach ($this->config['exec'] as $key => $value) {

            $workOne['name']    =$value['name'];
            $workOne['max_request'] =$value['max_request'];
            //子进程带上通用识别文字，方便ps查询进程
            // $workOne['binArgs']=array_merge($value['binArgs'], [$this->processName]);
            //开启多个子进程
            for ($i = 0; $i < $value['workNum']; $i++) {
                $this->reserveExec($i, $workOne);
            }
            $this->configWorkersByNameNum[$value['name']] = $value['workNum'];
        }

        if (empty($this->timer)) {
            $this->registSignal();
            $this->registTimer();
        }//启动成功，修改状态

        $this->saveMasterData([self::MASTER_KEY=>self::STATUS_RUNNING]);
    }

    public function startByWorkerName($workName)
    {
        $this->saveWorkerStatus([self::WORKER_STATUS_KEY . $workName=>self::STATUS_START]);
        foreach ($this->config['exec'] as $key => $value) {
            if ($value['name'] != $workName) {
                continue;
            }

            $workOne['name'] =$value['name'];
            $workOne['max_request'] =$value['max_request'];
            //子进程带上通用识别文字，方便ps查询进程
            // $workOne['binArgs']=array_merge($value['binArgs'], [$this->processName]);
            //开启多个子进程
            for ($i = 0; $i < $value['workNum']; $i++) {
                $this->reserveExec($i, $workOne);
            }
        }

        $this->saveWorkerStatus([self::WORKER_STATUS_KEY . $workName=>self::STATUS_RUNNING]);
    }

    /**
     * 启动子进程，跑业务代码
     *
     * @param [type] $num
     * @param [type] $workOne
     * @param mixed  $workNum
     */
    public function reserveExec($workNum, $workOne)
    {
        $reserveProcess = new \Swoole\Process(function ($worker) use ($workNum, $workOne) {
            $this->checkMpid($worker);
            //$beginTime=microtime(true);
            try {
                $job = new Jobs( $workOne );

                $num = 0;
                do {
                    echo "开始: ".memory_get_usage()." 字节 \n";
                    $job->run();

                    $this->status=$this->getMasterData(self::MASTER_KEY);
                    $flag = ( self::STATUS_RUNNING == $this->status ) ? true : false ;

                    // 计算进程最大请求数
                    if ( self::STATUS_RUNNING == $this->status && $num > $workOne['max_request'] ) {
                        $flag = false;
                    }

                    //echo $num ."===". "\n";
                    echo Utils::getMemoryUsage(). $worker->pid. "内存使用 \n";


                    /*
                    */
                    // echo "最终: ".memory_get_usage()." 字节 \n";
                    echo "内存总量: ".memory_get_peak_usage()." 字节 \n";
                    usleep( $this->sleepTime );
                    ++$num;
                } while ( $flag );


            } catch (\Throwable $e) {
                Utils::catchError($this->logger, $e);
            } catch (\Exception $e) {
                Utils::catchError($this->logger, $e);
            }
            $this->logger->log('worker id: ' . $workNum . ' is done!!!', 'info', $this->logSaveFileWorker);
            $worker->exit(0);
        });
        $pid = $reserveProcess->start();
        $this->workers[$pid] = $reserveProcess;
        $this->setWorkerList(self::REDIS_WORKER_MEMBER_KEY . $workOne['name'], $pid, 'add');
        $this->workersByPidName[$pid] =$workOne['name'];
        $this->saveWorkerStatus([self::WORKER_STATUS_KEY . $workOne['name'] =>self::STATUS_RUNNING]);
        $this->logger->log('worker id: ' . $workNum . ' pid: ' . $pid . ' is start...', 'info', $this->logSaveFileWorker);
    }

    //注册信号
    public function registSignal()
    {
        \Swoole\Process::signal(SIGTERM, function ($signo) {
            $this->killWorkersAndExitMaster();
        });

        \Swoole\Process::signal(SIGKILL, function ($signo) {
            $this->killWorkersAndExitMaster();
        });

        \Swoole\Process::signal(SIGUSR1, function ($signo) {
            $this->waitWorkers();
        });

        \Swoole\Process::signal(SIGCHLD, function ($signo) {
            while (true) {
                $ret = \Swoole\Process::wait(false);
                if ($ret) {
                    $pid           = $ret['pid'];
                    $childProcess = $this->workers[$pid];
                    $workName=$this->workersByPidName[$pid];
                    $this->status=$this->getMasterData(self::MASTER_KEY);
                    //根据wokerName，获取其运行状态
                    $workNameStatus=$this->getWorkerStatus(self::WORKER_STATUS_KEY . $workName);
                    //主进程状态为start,running且子进程组不是recover状态才需要拉起子进程
                    if ($workNameStatus != Process::STATUS_RECOVER && ($this->status == Process::STATUS_RUNNING || $this->status == Process::STATUS_START)) {
                        try {
                            $i=0;
                            //重启有可能失败，最多尝试10次
                            while ($i <= 10) {
                                $newPid  = $childProcess->start();
                                if ($newPid > 0) {
                                    break;
                                }
                                $this->logger->log($workName . '子进程重启失败，子进程尝试' . $i . '次重启', 'info', $this->logSaveFileWorker);

                                $i++;
                            }
                        } catch (\Throwable $e) {
                            Utils::catchError($this->logger, $e, 'error: woker restart fail...');
                        } catch (\Exception $e) {
                            Utils::catchError($this->logger, $e, 'error: woker restart fail...');
                        }
                        if ($newPid > 0) {
                            $this->logger->log("Worker Restart, kill_signal={$ret['signal']} PID=" . $newPid, 'info', $this->logSaveFileWorker);
                            $this->workers[$newPid] = $childProcess;
                            $this->setWorkerList(self::REDIS_WORKER_MEMBER_KEY . $workName, $newPid, 'add');
                            $this->workersByPidName[$newPid]        =$workName;
                            $this->saveWorkerStatus([self::WORKER_STATUS_KEY . $workName=>Process::STATUS_RUNNING]);
                        } else {
                            $this->saveWorkerStatus([self::WORKER_STATUS_KEY . $workName=>Process::STATUS_RECOVER]);
                            $this->logger->log($workName . '子进程重启失败，该组子进程进入recover状态', 'info', $this->logSaveFileWorker);
                        }
                    }
                    $this->logger->log("Worker Exit, kill_signal={$ret['signal']} PID=" . $pid, 'info', $this->logSaveFileWorker);
                    unset($this->workers[$pid], $this->workersByPidName[$pid]);
                    $this->setWorkerList(self::REDIS_WORKER_MEMBER_KEY . $workName, $pid, 'del');
                    $this->logger->log('Worker count: ' . count($this->workers) . '  [' . $workName . ']  ' . $this->configWorkersByNameNum[$workName], 'info', $this->logSaveFileWorker);
                    //如果$this->workers为空，且主进程状态为wait，说明所有子进程安全退出，这个时候主进程退出
                    if (empty($this->workers) && $this->status == Process::STATUS_WAIT) {
                        $this->logger->log('主进程收到所有信号子进程的退出信号，子进程安全退出完成', 'info', $this->logSaveFileWorker);
                        $this->exitMaster();
                    }
                } else {
                    break;
                }
            }
        });
    }

    public function registTimer()
    {
        $this->timer=\Swoole\Timer::tick($this->checkTickTimer, function ($timerId) {
            foreach ($this->configWorkersByNameNum as $workName => $value) {
                $this->status  =$this->getMasterData(self::MASTER_KEY);
                $workNameStatus=$this->getWorkerStatus(self::WORKER_STATUS_KEY . $workName);
                $workNameMembers=$this->getWorkerList(self::REDIS_WORKER_MEMBER_KEY . $workName);
                $this->checkChildProcess($workName, $workNameMembers);
                $count=count($workNameMembers);
                if ($count <= 0) {
                    $this->saveWorkerStatus([self::WORKER_STATUS_KEY . $workName=>Process::STATUS_START]);
                    $this->startByWorkerName($workName);
                    $this->logger->log('主进程 recover 子进程：' . $workName, 'info', $this->logSaveFileWorker);
                }
                $this->logger->log('主进程状态：' . $this->status . ' 数量：' . count($this->workers), 'info', $this->logSaveFileWorker);
                $this->logger->log('[' . $workName . ']子进程状态：' . $workNameStatus . ' 数量：' . $count . ' pids:' . serialize($workNameMembers), 'info', $this->logSaveFileWorker);
            }
        });
    }

    //检查子进程是否还活着
    private function checkChildProcess($workName, $members)
    {
        foreach ($members as $key => $pid) {
            if ($pid) {
                if (!@\Swoole\Process::kill($pid, 0)) {
                    unset($this->workers[$pid], $this->workersByPidName[$pid]);
                    $this->setWorkerList(self::REDIS_WORKER_MEMBER_KEY . $workName, $pid, 'del');
                    $this->logger->log('子进程异常退出：' . $pid . ' name：' . $workName, 'error', $this->logSaveFileWorker);
                } else {
                    $this->logger->log('子进程正常：' . $pid . ' name：' . $workName, 'info', $this->logSaveFileWorker);
                }
            }
        }
    }

    //平滑等待子进程退出之后，再退出主进程
    private function killWorkersAndExitMaster()
    {
        //修改主进程状态为stop
        $this->status              =self::STATUS_STOP;
        $this->saveMasterData([self::MASTER_KEY=>self::STATUS_STOP]);

        if ($this->workers) {
            foreach ($this->workers as $pid => $worker) {
                //强制杀workers子进程
            if (\Swoole\Process::kill($pid) == true) {
                unset($this->workers[$pid]);
                $this->logger->log('子进程[' . $pid . ']收到强制退出信号,退出成功', 'info', $this->logSaveFileWorker);
            } else {
                $this->logger->log('子进程[' . $pid . ']收到强制退出信号,但退出失败', 'info', $this->logSaveFileWorker);
            }

                $this->logger->log('Worker count: ' . count($this->workers), 'info', $this->logSaveFileWorker);
            }
        }
        $this->exitMaster();
    }

    //强制杀死子进程并退出主进程
    private function waitWorkers()
    {
        //修改主进程状态为wait

        $this->saveMasterData([self::MASTER_KEY=>self::STATUS_WAIT]);
        $this->status = self::STATUS_WAIT;
        foreach ($this->configWorkersByNameNum as $key => $value) {
            $workName                  =$key;
            $this->saveWorkerStatus([self::WORKER_STATUS_KEY . $workName=>self::STATUS_WAIT]);
        }
    }

    //退出主进程
    private function exitMaster()
    {
        @unlink($this->pidFile);
        //退出主程  删除掉其他info文件信息 by:xiaoliang
        @unlink($this->pidInfoFile);
        @unlink($this->workerStatusFile);
        $this->clearMasterData();
        $this->logger->log('Time: ' . microtime(true) . '主进程' . $this->ppid . '退出', 'info', $this->logSaveFileWorker);
        sleep(1);
        exit();
    }

    /**
     * 设置进程名.
     *
     * @param mixed $name
     */
    private function setProcessName($name)
    {
        //mac os不支持进程重命名
        if (function_exists('swoole_set_process_name') && PHP_OS != 'Darwin') {
            swoole_set_process_name($name);
        }
    }

    //主进程如果不存在了，子进程退出
    private function checkMpid(&$worker)
    {
        if (!@\Swoole\Process::kill($this->ppid, 0)) {
            $worker->exit();
            $this->logger->log("Master process exited, I [{$worker['pid']}] also quit");
        }
    }

    private function saveMasterPid()
    {
        file_put_contents($this->pidFile, $this->ppid);
    }

    private function getMasterPid()
    {
        return file_get_contents($this->pidFile);
    }

    private function saveMasterData($data=[])
    {

        file_put_contents($this->pidInfoFile, serialize($data));
    }

    private function clearMasterData()
    {
        $this->redis = $this->getRedis();

        $data=$this->configWorkersByNameNum;
        foreach ((array) $data as $key => $value) {
            // $value && $this->redis->del(self::WORKER_STATUS_KEY . $key);
            $value && $this->redis->del(self::REDIS_WORKER_MEMBER_KEY . $key);
            $this->logger->log('主进程退出前删除woker redis key： ' . $key, 'info', $this->logSaveFileWorker);
        }
        //$this->redis->del(self::MASTER_KEY);

        $this->logger->log('主进程退出前删除master redis key： status', 'info', $this->logSaveFileWorker);
    }

    private function setWorkerList($key, $member, $opt='add')
    {
        $this->redis = $this->getRedis();
        if ($opt == 'add') {
            return $this->redis->sAdd($key, $member);
        } elseif ($opt == 'del') {
            return $this->redis->sRemove($key, $member);
        }
    }

    private function getWorkerList($key)
    {
        $this->redis = $this->getRedis();

        return $this->redis->sMembers($key);
    }

    /*
     * 获取主程状态
     * by:xiaoliang
     * */
    private function getMasterData($key)
    {
        if ( !file_exists( $this->pidInfoFile ) ) {
            return null;
        }
        $data=unserialize(file_get_contents($this->pidInfoFile));

        if ($key) {
            return $data[$key] ?? null;
        }

        return $data;
    }


    /*
     * 保存worker状态
     * @param $data [] 数组
     * @return put 序列化数据
     * */
    private function saveWorkerStatus($data=[]) {

        $this->redis   = $this->getRedis();
        foreach ((array) $data as $key => $value) {
            $key && $this->redis->set($key, $value);
        }
        /*
        $mergeData = $data;
        // 检查序列化文件，保存所有任务状态
        if ( file_exists( $this->workerStatusFile ) ) {
            $mergeData = array_merge(
                $data,
                unserialize( file_get_contents($this->workerStatusFile) )
            );
        }

        file_put_contents($this->workerStatusFile, serialize($mergeData));
        */
    }

    /*
     * 获取 worker状态
     * @param $key string key
     * @return string
     * */
    private function getWorkerStatus($key) {

        $this->redis = $this->getRedis();
        if ($key) {
            return $this->redis->get($key);
        }
        /*
        $data=unserialize(file_get_contents($this->workerStatusFile));

        if ($key) {
            return $data[$key] ?? null;
        }

        return $data;
        */
    }



    private function getRedis()
    {
        if ($this->redis && $this->redis->ping()) {
            return $this->redis;
        }
        $this->redis   = new XRedis($this->config['redis']);

        return $this->redis;
    }
}
