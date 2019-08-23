<?php
require_once './vendor/autoload.php';
ini_set('memory_limit', '640M');
$worker = new \GlobalData\Server('0.0.0.0:30000');
\Workerman\Worker::runAll();