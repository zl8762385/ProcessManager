<?php
/*
 * 进程工作文件
 * @by xiaoliang
 * */
namespace Kcloze\MultiProcess;

use Pheanstalk\Exception;

class Jobs {

    // task业务工作目录
    private $taskWorker = "";

    // worker配置文件
    private $workerOne= [];

    // 脚本名称
    private $taskFile = "";

    public function __construct( & $workerOne ){
        $this->config = Config::getConfig();
        $this->workerOne = $workerOne;

        $this->logger = new Logs(Config::getConfig()['logPath'] ?? '', $this->config['logSaveFileApp'] ?? '');

        $this->taskFile = $this->workerOne['name'] ?? '';
        $this->require_file($this->taskFile . ".php");
    }

    /*
     * 运行单个进程作业
     * @return call func
     * */
    public function run () {

        try {
            $taskJobs= new $this->taskFile();
            call_user_func_array( array( $taskJobs, "run"), [] );
        } catch( \Throwable $e ) {
            Utils::catchError($this->logger, $e);
        } catch( \Exception $e ) {
            Utils::catchError($this->logger, $e);

        }

    }

    /*
     * 调用文件
     * @param $file string 文件名称
     * @reutrn include
     * */
    public function require_file( $file = '' ) {
        $files=  $this->config['workerDir'] . "/" . $file;
        try {

            if ( file_exists( $files ) ) {
                require_once $files;
            } else {
                throw new \Exception("file is not exists");
            }
        } catch( \Throwable $e ) {
            Utils::catchError($this->logger, $e);
        } catch( \Exception $e ) {
            Utils::catchError($this->logger, $e);
        }

    }
}
