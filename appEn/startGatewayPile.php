<?php
require_once '../vendor/autoload.php';
require_once '../vendor/autoloader.php';
$gateway = new \GatewayWorker\Gateway("En100://0.0.0.0:9000");
$gateway->startPort = 3000;
$gateway->registerAddress = '127.0.0.1:8000';
$gateway->count = 1;
$gateway->pingInterval = 15;
$gateway->pingNotResponseLimit = 1;
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}