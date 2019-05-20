<?php
require_once '../vendor/autoload.php';
require_once '../vendor/autoloader.php';
$worker = new \vendor\globalData\server('127.0.0.1', 30001);
if (!defined('LINUX_START')) {
    \Workerman\Worker::runAll();
}