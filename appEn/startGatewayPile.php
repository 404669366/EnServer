<?php
require_once '../vendor/autoload.php';
require_once '../vendor/autoloader.php';
var_dump(\vendor\helper\redis::app()->hGet('ReceptionImages',1));
$gateway = new \GatewayWorker\Gateway("En100://0.0.0.0:20000");
$gateway->name = 'pile';
$gateway->startPort = 3000;
$gateway->registerAddress = '127.0.0.1:30000';
$gateway->count = 2;
$gateway->pingInterval = 15;
$gateway->pingNotResponseLimit = 1;
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}