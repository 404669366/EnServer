<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/4/1
 * Time: 11:20
 */

namespace vendor\handle;

use GatewayWorker\Lib\Gateway;
use vendor\helper\common;

class EnUser
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

    private static function beginCharge($client_id, $message)
    {
        $body = pack('I', time());
        $body .= pack('A16', $message['order_no']);
        $body .= pack('C', $message['gun_no']);
        $body .= pack('I', $message['user_id']);
        Gateway::sendToClient($message['pile'], ['command' => 601, 'body' => $body]);
        return common::send($client_id, [], true, '充电启动中,请稍后');
    }

    private static function endCharge($client_id, $message)
    {
        Gateway::sendToClient($message['pile'], ['command' => 602, 'body' => pack('C', $message['gun_no'])]);
        return common::send($client_id, [], true, '充电结束中,请稍后');
    }
}