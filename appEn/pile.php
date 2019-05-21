<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/5/17
 * Time: 15:42
 */
var_dump(serialize(0));
require_once "../vendor/autoload.php";

$worker = new \Workerman\Worker();
$worker->onWorkerStart = 'connect';

function connect()
{
    static $count = 0;

    if ($count++ >= 20000) return;
    //   tcp://47.99.36.149:20000
    $con = new \Workerman\Connection\AsyncTcpConnection('tcp://127.0.0.1:20000');
    $con->onConnect = function ($con) use ($count) {
        $no = 'pile_' . $count;
        $data = pack('A16', $no);
        $data .= pack('S', 201);
        $data .= pack('I', 0);
        $con->send($data);
        \Workerman\Lib\Timer::add(10, function () use ($count, $no, $con) {
            $data = pack('A16', $no);
            $data .= pack('S', 101);
            $data .= pack('I', 6);
            $data .= pack('I', time());
            $data .= pack('C', mt_rand(1, 200));
            $data .= pack('C', mt_rand(1, 200));
            $con->send($data);
        });
    };
    $con->onMessage = function ($con, $data) use ($count) {
        echo $count.' is ok'.PHP_EOL;
        /*if (strlen($data) >= 22) {
            if ($head = @unpack('A16no/Scommand/Ilength', substr($data, 0, 22))) {
                $body = substr($data, 23);
                if (strlen($body) == $head['length']) {
                    switch ($head['command']) {
                        case 601;

                            break;
                        case 602;

                            break;
                    }
                }
            }
        }*/
    };
    $con->connect();
    connect();
}

\Workerman\Worker::runAll();