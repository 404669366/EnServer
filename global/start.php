<?php
require_once '../vendor/autoload.php';
require_once '../vendor/autoloader.php';
ini_set('memory_limit', '640M');
$worker = new \vendor\globalData\server('0.0.0.0:30001');
\Workerman\Worker::runAll();