<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/4/1
 * Time: 11:20
 */

namespace handle;

use GatewayWorker\Lib\Gateway;
use vendor\globalData\client;
use vendor\helper\common;

class EnUse
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
                    Gateway::joinGroup($client_id, $message['pile']);
                    return self::$funcName($client_id, $message);
                }
                return common::sendToClient($client_id, [], false, '电桩已掉线,请稍后再试');
            }
        }
        return common::sendToClient($client_id, [], false, '命令错误');
    }

    /**
     * @param $client_id
     */
    public static function onClose($client_id)
    {

    }

    /**
     * 查询充电
     * @param $client_id
     * @param $message
     */
    private static function queryCharge($client_id, $message)
    {
        Gateway::joinGroup($client_id, $message['pile'] . $message['gun_no']);
    }

    /**
     * 开始充电
     * @param $client_id
     * @param $message
     */
    private static function beginCharge($client_id, $message)
    {
        Gateway::joinGroup($client_id, $message['pile'] . $message['gun_no']);
        $body = pack('I', time());
        $body .= pack('A16', $message['order_no']);
        $body .= pack('C', $message['gun_no']);
        $body .= pack('I', $message['user_id']);
        Gateway::sendToClient($message['pile'], ['command' => 601, 'body' => $body]);
    }

    /**
     * 结束充电
     * @param $client_id
     * @param $message
     */
    private static function endCharge($client_id, $message)
    {
        Gateway::joinGroup($client_id, $message['pile'] . $message['gun_no']);
        $data = pack('C', $message['gun_no']);
        $data += pack('C', $message['source']);
        Gateway::sendToClient($message['pile'], ['command' => 602, 'body' => $data]);
    }

    /**
     * 重启电桩
     * @param $client_id
     * @param $message
     */
    private static function restart($client_id, $message)
    {
        Gateway::sendToClient($message['pile'], ['command' => 203, 'body' => '']);
    }

    /**
     * 设置编号及二维码
     * @param $client_id
     * @param $message
     */
    private static function setQrCode($client_id, $message)
    {
        $body = pack('A16', $message['no']);
        $body .= pack('C', count($message['qrCode']));
        foreach ($message['qrCode'] as $v) {
            $length = strlen($v);
            $body .= pack('S', $length);
            $body .= pack('A' . $length, $v);
        }
        Gateway::sendToClient($message['pile'], ['command' => 301, 'body' => $body]);
    }

    /**
     * 设置心跳间隔
     * @param $client_id
     * @param $message
     */
    private static function setInterval($client_id, $message)
    {
        $body = pack('S', $message['ordinary']);
        $body .= pack('S', $message['charge']);
        $body .= pack('S', $message['fault']);
        Gateway::sendToClient($message['pile'], ['command' => 401, 'body' => $body]);
    }

    /**
     * 查询时间段
     * @param $client_id
     * @param $message
     */
    private static function querySection($client_id, $message)
    {
        Gateway::sendToClient($message['pile'], ['command' => 501, 'body' => '']);
    }

    /**
     * 设置时间段
     * @param $client_id
     * @param $message
     */
    private static function setSection($client_id, $message)
    {
        $body = pack('C', count($message['section']));
        foreach ($message['section'] as $v) {
            $body .= pack('I', $v['begin']);
            $body .= pack('I', $v['end']);
        }
        Gateway::sendToClient($message['pile'], ['command' => 503, 'body' => $body]);
    }
}