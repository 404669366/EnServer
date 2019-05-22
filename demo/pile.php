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
    static $count = 0;

    if ($count++ >= 200) return;

    $no = 'pile_' . $count;
    $con = new \Workerman\Connection\AsyncTcpConnection('tcp://127.0.0.1:20000');//tcp://47.99.36.149:20000
    $con->onConnect = function ($con) use ($count, $no) {
        $data = pack('A16', $no);
        $data .= pack('S', 201);
        $data .= pack('I', 0);
        $con->send($data);
        \Workerman\Lib\Timer::add(2, function () use ($count, $no, $con) {
            $data = pack('A16', $no);
            $data .= pack('S', 101);
            $data .= pack('I', 6);
            $data .= pack('I', time());
            $data .= pack('C', mt_rand(1, 200));
            $data .= pack('C', mt_rand(1, 200));
            $con->send($data);
        });
    };
    $con->onMessage = function ($con, $data) use ($count, $no) {
        var_dump($count);
        if (strlen($data) >= 22) {
            if ($head = @unpack('A16no/Scommand/Ilength', substr($data, 0, 22))) {
                $body = substr($data, 22);
                if (strlen($body) == $head['length']) {
                    switch ($head['command']) {
                        case 202;
                            $body = @unpack('Itime', $body);
                            var_dump($head + $body);
                            break;
                        case 203;
                            var_dump($head);
                            $data = pack('A16', $no);
                            $data .= pack('S', 204);
                            $data .= pack('I', 1);
                            $data .= pack('C', 0);
                            $con->send($data);
                            break;
                        case 301;
                            $num = @unpack('A16new_no/Cnum', substr($body, 0, 17));
                            $guns = [];
                            $begin = 17;
                            for ($i = 0; $i < $num['num']; $i++) {
                                $length = @unpack('Slength', substr($body, $begin, 2));
                                $guns['code' . ($i + 1)] = @unpack('A' . $length['length'] . 'code', substr($body, $begin + 2, $length['length']))['code'];
                                $begin = $begin + 2 + $length['length'];
                            }
                            var_dump($head + $num + $guns);
                            $data = pack('A16', $no);
                            $data .= pack('S', 302);
                            $data .= pack('I', 1);
                            $data .= pack('C', 0);
                            $con->send($data);
                            break;
                        case 401;
                            $body = @unpack('Stime1/Stime2/Stime3', $body);
                            var_dump($head + $body);
                            $data = pack('A16', $no);
                            $data .= pack('S', 402);
                            $data .= pack('I', 1);
                            $data .= pack('C', 0);
                            $con->send($data);
                            break;
                        case 501;
                            var_dump($head);
                            $data = pack('A16', $no);
                            $data .= pack('S', 502);
                            $data .= pack('I', 17);
                            $data .= pack('C', 2);
                            $data .= pack('I', 0);
                            $data .= pack('I', 43200);
                            $data .= pack('I', 43200);
                            $data .= pack('I', 86400);
                            $con->send($data);
                            break;
                        case 503;
                            $count = @unpack('Ccount', substr($body, 0, 1));
                            $times = [];
                            $begin = 1;
                            for ($i = 1; $i <= $count['count']; $i++) {
                                $times['begin' . $i] = @unpack('Itime', substr($body, $begin, 4));
                                $times['end' . $i] = @unpack('Itime', substr($body, $begin + 4, 4));
                                $begin = $begin + 8;
                            }
                            var_dump($head + $count + $times);
                            $data = pack('A16', $no);
                            $data .= pack('S', 504);
                            $data .= pack('I', 1);
                            $data .= pack('C', 0);
                            $con->send($data);
                            break;
                        case 601;
                            $data = pack('A16', $no);
                            $data .= pack('S', 102);
                            $data .= pack('I', 1);
                            $data .= pack('C', 0);
                            $con->send($data);
                            break;
                        case 602;
                            $data = pack('A16', $no);
                            $data .= pack('S', 102);
                            $data .= pack('I', 1);
                            $data .= pack('C', 0);
                            $con->send($data);
                            break;
                    }
                }
            }
        }
    };
    $con->connect();
    connect();
}

\Workerman\Worker::runAll();