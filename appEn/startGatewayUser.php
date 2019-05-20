<?php
require_once '../vendor/autoload.php';
require_once '../vendor/autoloader.php';
$gateway = new \GatewayWorker\Gateway("websocket://0.0.0.0:20002");
$gateway->name = 'user';
$gateway->startPort = 3200;
$gateway->registerAddress = '127.0.0.1:30000';
$gateway->count = 1;
$gateway->pingInterval = 30;
$gateway->pingNotResponseLimit = 1;
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}