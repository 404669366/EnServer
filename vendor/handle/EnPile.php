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
use vendor\helper\redis;

class EnPile
{
    /**
     * @param int $client_id
     * @param array $message
     */
    public static function onMessage($client_id = 0, $message = [])
    {
        var_dump($message['no']);
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
        common::sendByPile($client_id, [], false, '电桩已下线');
        $global = new client();
        $global->__unset($client_id);
    }

    private static function command_101($client_id = 0, $message = [])
    {
        if ($body = @unpack('Itime/Cnum/Cfree', $message['body'])) {
            redis::app()->hSet(
                'pileInfo',
                $message['no'],
                json_encode([
                    'client_id' => $client_id,
                    'time' => $body['time'],
                    'num' => $body['num'],
                    'free' => $body['free']
                ])
            );
        }
    }

    private static function command_102($client_id = 0, $message = [])
    {
        if ($body = @unpack('Itime/Cgun/Cstatus/Scode/A16order/Iuser/Csoc/Ipower/Itemperature/Iduration/Isurplus/A17vin/Ccount', substr($message['body'], 0, 63))) {
            $surplusStructure = [];
            for ($i = 1; $i <= $body['count']; $i++) {
                $surplusStructure [] = 'Isection' . $i;
            }
            $surplusStructure = implode($surplusStructure, '/');
            if ($surplus = @unpack($surplusStructure, substr($message['body'], 64))) {
                $body += $surplus;
            }
        }
    }

    private static function command_103($client_id = 0, $message = [])
    {
        if ($body = @unpack('Itime/Ctype', substr($message['body'], 0, 5))) {
            $error = common::analysisErrorCode($body['type'], substr($message['body'], 6));
            $errorInfo = json_decode(redis::app()->hGet('pileError', $message['no']), true) ?: [0 => [], 1 => [], 2 => []];
            $errorInfo[$body['type']] = $error + $errorInfo[$body['type']];
            redis::app()->hSet('pileErrors', $message['no'], json_encode($errorInfo));
        }
    }

    private static function command_201($client_id = 0, $message = [])
    {
        Gateway::sendToClient($client_id, ['command' => 202, 'body' => pack('I', time())]);
    }

    private static function command_204($client_id = 0, $message = [])
    {
        if ($re = @unpack('C', $message['body'])) {
            return common::sendByPile($client_id, $message['command'], true, common::reStatus($re[1]));
        }
        return common::sendByPile($client_id, $message['command'], true, common::reStatus());
    }

    private static function command_302($client_id = 0, $message = [])
    {
        if ($re = @unpack('C', $message['body'])) {
            if ($re[1] == 0) {
                redis::app()->hDel('pileInfo', $message['no']);
            }
            return common::sendByPile($client_id, $message['command'], true, common::reStatus($re[1]));
        }
        return common::sendByPile($client_id, $message['command'], true, common::reStatus());
    }

    private static function command_402($client_id = 0, $message = [])
    {
        if ($re = @unpack('C', $message['body'])) {
            return common::sendByPile($client_id, $message['command'], true, common::reStatus($re[1]));
        }
        return common::sendByPile($client_id, $message['command'], true, common::reStatus());
    }

    private static function command_502($client_id = 0, $message = [])
    {
        if ($body = @unpack('Ccount', substr($message['body'], 0, 1))) {
            $surplusStructure = [];
            for ($i = 1; $i <= $body['count']; $i++) {
                $surplusStructure[] = 'Ibegin' . $i . '/Iend' . $i;
            }
            $surplusStructure = implode($surplusStructure, '/');
            if ($surplus = @unpack($surplusStructure, substr($message['body'], 64))) {
                $body += $surplus;
                return common::sendByPile($client_id, $body, true);
            }
        }
        return common::sendByPile($client_id, $message['command'], true, common::reStatus());
    }

    private static function command_504($client_id = 0, $message = [])
    {
        if ($re = @unpack('C', $message['body'])) {
            return common::sendByPile($client_id, $message['command'], true, common::reStatus($re[1]));
        }
        return common::sendByPile($client_id, $message['command'], true, common::reStatus());
    }
}