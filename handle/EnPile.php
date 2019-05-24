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
use vendor\helper\redis;

class EnPile
{
    /**
     * @param int $client_id
     * @param array $message
     */
    public static function onMessage($client_id = 0, $message = [])
    {
        $funcName = 'command_' . $message['command'];
        if (method_exists(new self(), $funcName)) {
            self::$funcName($client_id, $message);
        }
    }

    /**
     * @param $client_id
     */
    public static function onClose($client_id)
    {
        common::sendToGroup($client_id, 'onClose', '', '电桩掉线');
        (new client())->hSetField('pileInfo', $_SESSION['no'], 'isOnline', false);
    }

    /**
     * 普通心跳
     * @param int $client_id
     * @param array $message
     */
    private static function command_101($client_id = 0, $message = [])
    {
        if ($body = @unpack('Itime/Cnum/Cfree', $message['body'])) {
            $_SESSION['no'] = $message['no'];
            (new client())->hSet(
                'pileInfo',
                $message['no'],
                [
                    'client_id' => $client_id,
                    'no' => $message['no'],
                    'time' => $body['time'],
                    'num' => $body['num'],
                    'free' => $body['free'],
                    'isOnline' => true,
                ]
            );
        }
    }

    /**
     * 充电心跳
     * @param int $client_id
     * @param array $message
     * @return bool
     */
    private static function command_102($client_id = 0, $message = [])
    {
        $bodyStructure = 'Itime/Cgun/Cstatus/Scode/A16order/Iuser/Csoc/Ipower/Itemperature/Iduration/Isurplus/A17vin/Ccount';
        if ($body = @unpack($bodyStructure, substr($message['body'], 0, 63))) {
            $surplusStructure = [];
            for ($i = 1; $i <= $body['count']; $i++) {
                $surplusStructure[] = 'Isection' . $i;
            }
            $surplusStructure = implode($surplusStructure, '/');
            if ($surplus = @unpack($surplusStructure, substr($message['body'], 63))) {
                $body += $surplus;
                return common::sendToGroup($client_id . $body['gun'], 'charge', $body);
            }
            return common::sendToGroup($client_id . $body['gun'], 'charge', '', '数据解析错误');
        }
        return common::sendToGroup($client_id, 'charge', '', '数据解析错误');
    }

    /**
     * 故障心跳
     * @param int $client_id
     * @param array $message
     */
    private static function command_103($client_id = 0, $message = [])
    {
        if ($body = @unpack('Itime/Ctype', substr($message['body'], 0, 5))) {
            $error = common::analysisErrorCode($body['type'], substr($message['body'], 5));
            $errorInfo = json_decode(redis::app()->hGet('pileError', $message['no']), true) ?: [0 => [], 1 => [], 2 => []];
            $errorInfo[$body['type']] = $error + $errorInfo[$body['type']];
            redis::app()->hSet('pileErrors', $message['no'], json_encode($errorInfo));
        }
    }

    /**
     * 返回校时
     * @param int $client_id
     * @param array $message
     */
    private static function command_201($client_id = 0, $message = [])
    {
        Gateway::sendToClient($client_id, ['command' => 202, 'body' => pack('I', time())]);
    }

    /**
     * 重启回馈
     * @param int $client_id
     * @param array $message
     * @return bool
     */
    private static function command_204($client_id = 0, $message = [])
    {
        if ($re = @unpack('Cstatus', $message['body'])) {
            return common::sendToGroup($client_id, 'restart', $re);
        }
        return common::sendToGroup($client_id, 'restart', '', '数据解析错误');
    }

    /**
     * 设置编号及二维码回馈
     * @param int $client_id
     * @param array $message
     * @return bool
     */
    private static function command_302($client_id = 0, $message = [])
    {
        if ($re = @unpack('Cstatus', $message['body'])) {
            if ($re['status'] == 0) {
                $global = new client();
                $global->hDel('pileInfo', $message['no']);
            }
            return common::sendToGroup($client_id, 'setNoAndQrCode', $re);
        }
        return common::sendToGroup($client_id, 'setNoAndQrCode', '', '数据解析错误');
    }

    /**
     * 设置心跳间隔回馈
     * @param int $client_id
     * @param array $message
     * @return bool
     */
    private static function command_402($client_id = 0, $message = [])
    {
        if ($re = @unpack('Cstatus', $message['body'])) {
            return common::sendToGroup($client_id, 'setInterval', $re);
        }
        return common::sendToGroup($client_id, 'setInterval', '', '数据解析错误');
    }

    /**
     * 时间段查询回馈
     * @param int $client_id
     * @param array $message
     * @return bool
     */
    private static function command_502($client_id = 0, $message = [])
    {
        if ($body = @unpack('Ccount', substr($message['body'], 0, 1))) {
            $surplusStructure = [];
            for ($i = 1; $i <= $body['count']; $i++) {
                $surplusStructure[] = 'Ibegin' . $i . '/Iend' . $i;
            }
            $surplusStructure = implode($surplusStructure, '/');
            if ($surplus = @unpack($surplusStructure, substr($message['body'], 1))) {
                $body += $surplus;
                return common::sendToGroup($client_id, 'querySection', $body);
            }
        }
        return common::sendToGroup($client_id, 'querySection', '', '数据解析错误');
    }

    /**
     * 时间段设置回馈
     * @param int $client_id
     * @param array $message
     * @return bool
     */
    private static function command_504($client_id = 0, $message = [])
    {
        if ($re = @unpack('Cstatus', $message['body'])) {
            return common::sendToGroup($client_id, 'setSection', $re);
        }
        return common::sendToGroup($client_id, 'setSection', '', '数据解析错误');
    }
}