<?php
function test0() {
    $infos = 'test - 0 time: ' . date('Y-m-d H:i:s')."\n";
    file_put_contents('a12.txt',$infos . PHP_EOL, FILE_APPEND);
}


function test1() {
    $infos = 'test - 1 time: ' . date('Y-m-d H:i:s')."\n";
    file_put_contents('a12.txt',$infos . PHP_EOL, FILE_APPEND);
}
