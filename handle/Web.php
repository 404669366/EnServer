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

class Web
{
    /**
     * @param int $client_id
     * @param string $message
     * @return bool|mixed
     */
    public static function onMessage($client_id = 0, $message = '')
    {
        $message = json_decode($message, true);
        if (isset($message['do']) && $message['do'] && method_exists(self::class, $message['do'])) {
            return call_user_func_array('self::' . $message['do'], [$client_id, $message]);
        }
        return Gateway::sendToClient($client_id, json_encode(['code' => 100]));
    }

    /**
     * @param $client_id
     */
    public static function onClose($client_id)
    {

    }

    /**
     * 开始充电
     * @param $client_id
     * @param $message [] pile gun orderNo uid
     * @return bool
     */
    private static function beginCharge($client_id, $message)
    {
        
        if (Gateway::isUidOnline($message['pile'])) {
            $userMoney = (new client())->hGetField('UserInfo', $message['uid'], 'money') ?: 0;
            if ($userMoney > 1) {
                $session = self::getSessionByUid($message['pile']);
                if ($session['carStatus'] != 0) {
                    if ($session['workStatus'] == 0) {
                        Gateway::joinGroup($client_id, $message['orderNo']);
                        Gateway::sendToUid($message['pile'], ['cmd' => 7, 'params' => [$message['gun'], $message['uid'], $message['orderNo']]]);
                        return Gateway::sendToClient($client_id, json_encode(['code' => 204]));
                    }
                    return Gateway::sendToClient($client_id, json_encode(['code' => 203]));
                }
                return Gateway::sendToClient($client_id, json_encode(['code' => 202]));
            }
            return Gateway::sendToClient($client_id, json_encode(['code' => 201]));
        }
        return Gateway::sendToClient($client_id, json_encode(['code' => 200]));
    }

    /**
     * 结束充电
     * @param $client_id
     * @param $message
     */
    private static function endCharge($client_id, $message)
    {
        if (Gateway::isUidOnline($message['pile'])) {
            Gateway::joinGroup($client_id, $message['pile'] . $message['gun'] . '_endCharge');
            Gateway::sendToUid($message['pile'], ['cmd' => 5, 'params' => [$message['gun'], 2, 85]]);
            return;
        }
        Gateway::sendToClient($client_id, json_encode(['code' => 301]));
        return;
    }

    /**
     * 查看充电
     * @param $client_id
     * @param $message
     */
    private static function seeCharge($client_id, $message)
    {
        Gateway::joinGroup($client_id, $message['orderNo']);
    }

    /**
     * 查询在线电桩
     * @param $client_id
     * @param $message
     */
    private static function pileList($client_id, $message)
    {
        Gateway::joinGroup($client_id, 'pileList');
        Gateway::sendToClient($client_id, json_encode(['code' => 500, 'data' => Gateway::getAllUidList()]));
    }

    /**
     * 查询电桩信息
     * @param $client_id
     * @param $message
     */
    private static function pileInfo($client_id, $message)
    {
        if (Gateway::isUidOnline($message['pile'])) {
            Gateway::joinGroup($client_id, $message['pile'] . '_pileInfo');
            Gateway::sendToClient($client_id, json_encode(['code' => 600, 'data' => self::getSessionByUid($message['pile'])]));
        }
        Gateway::sendToClient($client_id, json_encode(['code' => 601]));
        return;
    }

    /**
     * 设置编号
     * @param $client_id
     * @param $message
     */
    private static function setNo($client_id, $message)
    {
        if (Gateway::isUidOnline($message['pile'])) {
            Gateway::joinGroup($client_id, $message['pile'] . '_setNo');
            Gateway::sendToUid($message['pile'], ['cmd' => 3, 'params' => [1, 1, pack('a32', $message['no'])]]);
            return;
        }
        Gateway::sendToClient($client_id, json_encode(['code' => 401]));
        return;
    }

    /**
     * 获取uid session
     * @param string $uid
     * @return array|mixed
     */
    private static function getSessionByUid($uid = '')
    {
        if ($clients = Gateway::getClientIdByUid($uid)) {
            return Gateway::getSession($clients[0]);
        }
        return [];
    }
}