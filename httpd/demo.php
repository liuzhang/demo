<?php
/**
 * Created by PhpStorm.
 * User: liuzhang
 * Date: 2018/6/28
 * Time: 下午10:55
 */

require_once 'Woker.php';

$woker = new Worker('0.0.0.0:2345');
$woker->count = 4;
$woker->onMessage = function (Worker $connection) {
    $connection->send("Hi world\n");
};
$woker->init();

