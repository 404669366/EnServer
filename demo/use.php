<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/5/17
 * Time: 15:42
 */
require_once "../vendor/autoload.php";
require_once '../vendor/globalData/client.php';

$worker = new \Workerman\Worker();
$worker->onWorkerStart = 'connect';

function connect()
{
    static $count = 0;

    if ($count++ >= 10) return;
    $con = new \Workerman\Connection\AsyncTcpConnection('ws://127.0.0.1:20001');//ws://47.99.36.149:20001
    $con->onConnect = function ($con) {
        $global = new \vendor\globalData\client();
        \Workerman\Lib\Timer::add(1, function () use ($global, $con) {
            $pile = $global->hGet('pileInfo', 'pile_' . mt_rand(1, 200));
            var_dump($pile['client_id']);
            $con->send(json_encode([
                'pile' => $pile['client_id'],
                'do' => 'querySection',
            ]));
        });
    };
    $con->onMessage = function ($con, $data) {
        var_dump($data);
    };
    $con->connect();
    connect();
}

\Workerman\Worker::runAll();