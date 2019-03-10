<?php

/*
 * This file is part of PHP CS Fixer.
 * (c) kcloze <pei.greet@qq.com>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

ini_set('date.timezone', 'Asia/Shanghai');
$infos = 'test - 2 time: ' . date('Y-m-d H:i:s')."\n";
file_put_contents('a12.txt',$infos . PHP_EOL, FILE_APPEND);
// while (true) {
//     echo '123' . PHP_EOL;
//     sleep(1);
// }
// sleep(10);

// $i= mt_rand(1, 5);
// var_dump($i);
// if ($i == 3) {
//     NotExit();
// }
