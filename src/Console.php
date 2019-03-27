<?php
/*
 * 控制台
 * @author xiaoliang
 * 您可以自由使用该源码，但是在使用过程中，请保留作者信息。尊重他人劳动成果就是尊重自己
 * */

namespace Clever\ProcessManager;

class Console
{
    public $logger    = null;
    private $config   = [];
    private $opt      = [];
    private $redis    = null;

    public function __construct($opt, $config)
    {
        $this->opt=$opt;
        if (empty($this->opt)) {
            $this->printHelpMessage();
            exit(1);
        }
        Config::setConfig($config);
        $this->config = Config::getConfig();
        $this->logger = new Logs(Config::getConfig()['logPath'] ?? '', $this->config['logSaveFileApp'] ?? '');
    }

    public function run()
    {
        $this->runOpt();
    }

    public function start()
    {
        //启动
        $process = new Process();
        $process->start();
    }

    /**
     * 给主进程发送信号：
     *  SIGUSR1 自定义信号，让子进程平滑退出
     *  SIGTERM 程序终止，让子进程强制退出.
     *
     * @param [type] $signal
     */
    public function stop($signal=SIGUSR1)
    {
        $this->logger->log(($signal == SIGUSR1) ? 'smooth to exit...' : 'force to exit...');

        if (isset($this->config['pidPath']) && !empty($this->config['pidPath'])) {
            $masterPidFile=$this->config['pidPath'] .'/'.$this->config['serviceMark'].'_master.pid';
        } else {
            die('config pidPath must be set!');
        }

        if (file_exists($masterPidFile)) {
            $ppid=file_get_contents($masterPidFile);
            if (empty($ppid)) {
                exit('service is not running' . PHP_EOL);
            }
            //给主进程发送信号
            if (@\Swoole\Process::kill($ppid, $signal)) {
                $this->logger->log('[pid: ' . $ppid . '] has been stopped success');
            } else {
                $this->logger->log('[pid: ' . $ppid . '] has been stopped fail');
            }
            //$this->getRedis()->set(Process::MASTER_KEY, Process::STATUS_WAIT);
            $this->saveMasterData([Process::MASTER_KEY=>Process::STATUS_WAIT]);
        } else {
            exit('service is not running' . PHP_EOL);
        }
    }

    public function restart()
    {
        $this->logger->log('restarting...');
        $this->exit();
        sleep(3);
        $this->start();
    }

    public function exit()
    {
        $this->stop(SIGTERM);
    }

    public function runOpt()
    {
        switch ($this->opt) {
            case 'start':
                $this->start();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'exit':
                $this->exit();
                break;
            case 'restart':
                $this->restart();
                break;
            case 'help':
                $this->printHelpMessage();
                break;

            default:
                $this->printHelpMessage();
                break;
        }
    }

    public function printHelpMessage()
    {
        $msg=<<<'EOF'
NAME
      php multiprocess - manage multiprocess

SYNOPSIS
      php multiprocess command [options]
          Manage multiprocess daemons.


WORKFLOWS


      help [command]
      Show this help, or workflow help for command.


      -s restart
      Stop, then start multiprocess master and workers.

      -s start 
      Start multiprocess master and workers.
      -s start -c=./config
      Start multiprocess with specail config file.

      -s stop
      Wait all running workers smooth exit, please check multiprocess status for a while.

      -s exit
      Kill all running workers and master PIDs.


EOF;
        echo $msg;
    }


    /*
     * 保存master状态
     * */
    private function saveMasterData($data=[]) {
        $mName = $this->config['serviceMark'] ?? '' ;
        $pidInfoFile =$this->config['pidPath'] . '/' . $mName .'_'. Process::PID_INFO_FILE;

        file_put_contents($pidInfoFile, serialize($data));
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
