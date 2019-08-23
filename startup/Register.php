<?php
if (!defined('LINUX_START')) {
    require_once './vendor/autoload.php';
}
$register = new \GatewayWorker\Register('text://127.0.0.1:20000');
$register->name = 'register';
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}