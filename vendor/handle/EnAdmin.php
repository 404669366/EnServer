<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/4/1
 * Time: 11:20
 */

namespace vendor\handle;

use GatewayWorker\Lib\Gateway;
use vendor\globalData\client;
use vendor\helper\common;

class EnAdmin
{
    /**
     * @param int $client_id
     * @param string $message
     * @return bool
     */
    public static function onMessage($client_id = 0, $message = '')
    {
        $message = json_decode($message, true);
        if (isset($message['do']) && $message['do'] && isset($message['pile']) && $message['pile']) {
            $funcName = $message['do'];
            if (method_exists(new self(), $funcName)) {
                if (Gateway::isOnline($message['pile'])) {
                    return self::$funcName($client_id, $message);
                }
                return common::send($client_id, [], false, '电桩已掉线,请稍后再试');
            }
        }
        return common::send($client_id, [], false, '命令错误');
    }

    /**
     * @param $client_id
     */
    public static function onClose($client_id)
    {

    }

    private static function restart($client_id, $message)
    {
        $global = new client();
        $global->$client_id = $message['pile'];
        return Gateway::sendToClient($message['pile'], ['command' => 203, 'body' => '']);
    }

    private static function setQrCode($client_id, $message)
    {
        $global = new client();
        $global->$client_id = $message['pile'];
        $body = pack('A16', $message['no']);
        $body .= pack('C', count($message['qrCode']));
        foreach ($message['qrCode'] as $v) {
            $length = strlen($v);
            $body .= pack('S', $length);
            $body .= pack('A' . $length, $v);
        }
        return Gateway::sendToClient($message['pile'], ['command' => 301, 'body' => $body]);
    }

    private static function setInterval($client_id, $message)
    {
        $global = new client();
        $global->$client_id = $message['pile'];
        $body = pack('S', $message['ordinary']);
        $body .= pack('S', $message['charge']);
        $body .= pack('S', $message['fault']);
        return Gateway::sendToClient($message['pile'], ['command' => 401, 'body' => $body]);
    }

    private static function querySection($client_id, $message)
    {
        $global = new client();
        $global->$client_id = $message['pile'];
        return Gateway::sendToClient($message['pile'], ['command' => 501, 'body' => '']);
    }

    private static function setSection($client_id, $message)
    {
        $global = new client();
        $global->$client_id = $message['pile'];
        $body = pack('C', count($message['section']));
        foreach ($message['section'] as $v) {
            $body .= pack('I', $v['begin']);
            $body .= pack('I', $v['end']);
        }
        return Gateway::sendToClient($message['pile'], ['command' => 503, 'body' => $body]);
    }
}