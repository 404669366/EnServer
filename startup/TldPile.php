<?php
if (!defined('LINUX_START')) {
    require_once './vendor/autoload.php';
}
$gateway = new \GatewayWorker\Gateway("Tld://0.0.0.0:20002");
$gateway->name = 'EldPile';
$gateway->startPort = 3000;
$gateway->registerAddress = '127.0.0.1:20000';
$gateway->count = 2;
$gateway->pingInterval = 30;
$gateway->pingNotResponseLimit = 1;
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}