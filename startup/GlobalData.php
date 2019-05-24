<?php
if (!defined('LINUX_START')) {
    require_once '../vendor/autoload.php';
    require_once '../vendor/autoloader.php';
}
ini_set('memory_limit', '640M');
$worker = new \vendor\globalData\server('0.0.0.0:30001');
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}