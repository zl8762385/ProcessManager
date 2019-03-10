<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

ini_set('date.timezone', 'Asia/Shanghai');

//echo 'test - 1 time: ' . date('Y-m-d H:i:s')."\n";
$infos = 'test - 1 time: ' . date('Y-m-d H:i:s')."\n";
file_put_contents('a12.txt',$infos . PHP_EOL, FILE_APPEND);

    //sleep(15);


// if ($i == 3) {
//     NotExit();
// }
