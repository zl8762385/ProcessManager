<?php
/*
 * 组件工具
 * @author xiaoliang
 * 您可以自由使用该源码，但是在使用过程中，请保留作者信息。尊重他人劳动成果就是尊重自己
 * */

namespace Clever\ProcessManager;

class Utils
{
    /**
     * 循环创建目录.
     * @param mixed $path
     * @param mixed $recursive
     * @param mixed $mode
     */
    public static function mkdir($path, $mode=0777, $recursive=true)
    {
        if (!is_dir($path)) {
            mkdir($path, $mode, $recursive);
        }
    }

    public static function catchError($logger, $exception, $error='')
    {
        $error .= '错误类型：' . get_class($exception) . PHP_EOL;
        $error .= '错误代码：' . $exception->getCode() . PHP_EOL;
        $error .= '错误信息：' . $exception->getMessage() . PHP_EOL;
        $error .= '错误堆栈：' . $exception->getTraceAsString() . PHP_EOL;

        $logger->log($error, 'error');
    }

     /*
      * 获取内存使用情况
      * @return string
      * */
    public static function getMemoryUsage() {
        // 类型是MB,获取时需要手动加上
        return round(memory_get_usage() / (1024 * 1024), 2);
    }
}
