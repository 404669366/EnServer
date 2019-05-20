<?php
require_once '../vendor/autoload.php';
require_once '../vendor/autoloader.php';
$register = new \GatewayWorker\Register('text://127.0.0.1:8000');
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}