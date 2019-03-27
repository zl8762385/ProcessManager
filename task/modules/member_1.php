<?php

class member_1 {

    public function __construct() {

    }

    public function run() {

        $rand = rand(0, 100);
        file_put_contents("/workspace/a12.txt", $rand. "=^=". PHP_EOL , FILE_APPEND);
        //sleep(5);
    }

}
