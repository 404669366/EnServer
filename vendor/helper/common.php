<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/5/8
 * Time: 16:01
 */

namespace vendor\helper;


use GatewayWorker\Lib\Gateway;
use vendor\globalData\client;

class common
{
    /**
     * 发送信息到后台/用户统一方法
     * @param string $client_id
     * @param string $do
     * @param array $data
     * @param string $msg
     * @return bool
     */
    public static function sendToClient($client_id = '', $do = '', $data = [], $msg = 'ok')
    {
        return Gateway::sendToClient($client_id, json_encode(['data' => $data, 'do' => $do, 'msg' => $msg]));
    }

    /**
     * 发送信息到对应电桩组下后台/用户统一方法
     * @param string $group
     * @param string $do
     * @param array $data
     * @param string $msg
     * @return bool
     */
    public static function sendToGroup($group = '', $do = '', $data = [], $msg = 'ok')
    {
        Gateway::sendToGroup($group, json_encode(['data' => $data, 'do' => $do, 'msg' => $msg]));
        return true;
    }

    /**
     * 解析故障码
     * @param int $type
     * @param array $data
     * @return array
     */
    public static function analysisErrorCode($type = 0, $data = [])
    {
        $result = [];
        foreach ($data as $k => $v) {
            $v = str_pad(decbin($v), 8, "0", STR_PAD_LEFT);
            for ($i = 0; $i < 8; $i++) {
                $result[$type . $k . ($i + 1)] = $v[$i];
            }
        }
        return $result;
    }
}