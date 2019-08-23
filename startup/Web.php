<?php
if (!defined('LINUX_START')) {
    require_once './vendor/autoload.php';
}
$gateway = new \GatewayWorker\Gateway("websocket://0.0.0.0:20001");
$gateway->name = 'web';
$gateway->registerAddress = '127.0.0.1:20000';
$gateway->count = 1;
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}