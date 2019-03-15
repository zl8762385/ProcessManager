<?php
/*
 * 进程工作文件
 * @by xiaoliang
 * */
namespace Clever\ProcessManager;

use Pheanstalk\Exception;

class Jobs {

    // task业务工作目录
    private $taskWorker = "";

    // worker配置文件
    private $workerOne= [];

    // 脚本名称
    private $taskFile = "";

    // 执行任务前，需要加载的外部框架文件
    private $workerLoadFileBefore = [];

    public function __construct( & $workerOne ){
        $this->config = Config::getConfig();
        $this->workerOne = $workerOne;

        $this->logger = new Logs(Config::getConfig()['logPath'] ?? '', $this->config['logSaveFileApp'] ?? '');

        $this->taskFile = $this->workerOne['name'] ?? '';

        if ( isset( $this->config['workerLoadFileBefore'] ) && !empty( $this->config['workerLoadFileBefore'] ) ) {
            $this->workerLoadFileBefore = $this->config['workerLoadFileBefore'];
        }


    }

    /*
     * 运行单个进程作业
     * @return call func
     * */
    public function run () {

        // 加载文件
        $this->loadFiles();

        try {
            // 执行run
            $taskJobs= new $this->taskFile();
            call_user_func_array( array( $taskJobs, "run"), [] );
        } catch( \Throwable $e ) {
            Utils::catchError($this->logger, $e);
        } catch( \Exception $e ) {
            Utils::catchError($this->logger, $e);

        }

    }

    /*
     * 加载文件
     * @return include
     * */
    private function loadFiles() {
        // load外部框架文件
        $this->loadFrameworkBefore();

        $this->require_file($this->taskFile . ".php");
    }

    /*
     * 加载任务前融合业务框架中的代码,如您需要在任务中执行您业务代码，请看这里
     * @return include
     * */
    private function loadFrameworkBefore() {

        foreach( $this->workerLoadFileBefore as $k => $file ) {
            $this->require_file( $file );
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
