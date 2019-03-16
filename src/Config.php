<?php
/*
 * Config基础
 * @author xiaoliang
 * 您可以自由使用该源码，但是在使用过程中，请保留作者信息。尊重他人劳动成果就是尊重自己
 * */

namespace Clever\ProcessManager;


class Config
{
    private static $config=[];

    public static function setConfig($config)
    {
        self::$config=$config;
    }

    public static function getConfig()
    {
        return self::$config;
    }

    public static function hasRepeatingName($config=[], $chckKey='name')
    {
        $nameList=[];
        foreach ($config as $key => $value) {
            if (isset($nameList[$value[$chckKey]])) {
                return true;
            }
            $nameList[$value[$chckKey]]=$value[$chckKey];
        }

        return false;
    }
}
