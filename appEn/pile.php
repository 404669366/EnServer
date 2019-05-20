<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/5/17
 * Time: 15:42
 */
require_once "../vendor/autoload.php";

$worker = new \Workerman\Worker();
$worker->onWorkerStart = 'connect';

function connect()
{
    static $count = 1200;

    if ($count++ >= 2400) return;

    $con = new \Workerman\Connection\AsyncTcpConnection('tcp://127.0.0.1:20000');
    $con->onConnect = function ($con) use ($count) {
        $no = 'pile_' . $count;
        $data = pack('A16', $no);
        $data .= pack('S', 201);
        $data .= pack('I', 0);
        $con->send($data);
        \Workerman\Lib\Timer::add(3, function () use ($count, $no, $con) {
            $data = pack('A16', $no);
            $data .= pack('S', 101);
            $data .= pack('I', 6);
            $data .= pack('I', time());
            $data .= pack('C', $count);
            $data .= pack('C', $count);
            $con->send($data);
        });
        connect();
    };
    $con->onMessage = function ($con, $data) use ($count) {
        echo $count . ' is ok' . PHP_EOL;
    };
    $con->connect();
}

\Workerman\Worker::runAll();