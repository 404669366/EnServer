<?php
require_once '../vendor/autoload.php';
require_once '../vendor/autoloader.php';
$worker = new \GatewayWorker\BusinessWorker();
$worker->registerAddress = '127.0.0.1:30000';
$worker->count = 2;
$worker->eventHandler = 'Events';
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}

