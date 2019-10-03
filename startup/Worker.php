<?php
if (!defined('LINUX_START')) {
    require_once './vendor/autoload.php';
}
$worker = new \GatewayWorker\BusinessWorker();
$worker->name = 'worker';
$worker->registerAddress = '127.0.0.1:20000';
$worker->count = 2;
$worker->eventHandler = '\startup\Events';
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}

