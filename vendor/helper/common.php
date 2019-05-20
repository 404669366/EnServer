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
     * @param array $data
     * @param bool $type
     * @param string $msg
     * @return bool
     */
    public static function send($client_id = '', $data = [], $type = true, $msg = 'ok')
    {
        return Gateway::sendToClient($client_id, json_encode(['data' => $data, 'type' => $type, 'msg' => $msg]));
    }

    /**
     * 通过电桩client_id发送信息到后台/用户统一方法
     * @param string $client_id
     * @param array $data
     * @param bool $type
     * @param string $msg
     * @return bool
     */
    public static function sendByPile($client_id = '', $data = [], $type = true, $msg = 'ok')
    {
        return Gateway::sendToClient((new client())->$client_id, json_encode(['data' => $data, 'type' => $type, 'msg' => $msg]));
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

    /**
     * 统一命令返回状态
     * @param int $status
     * @return mixed
     */
    public static function reStatus($status = 3)
    {
        $statuses = [
            0 => '设置成功',
            1 => '电桩繁忙',
            2 => '数据错误',
            3 => '系统错误',
        ];
        return $statuses[$status];
    }

    /**
     * 充电状态
     * @param int $status
     * @return mixed
     */
    public static function chargeStatus($status = 2)
    {
        $statuses = [
            0 => '正在启动',
            1 => '启动失败',
            2 => '正在充电',
            3 => '充电结束',
            4 => '异常结束',
        ];
        return $statuses[$status];
    }
}