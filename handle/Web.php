<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/4/1
 * Time: 11:20
 */

namespace handle;

use GatewayWorker\Lib\Gateway;
use GlobalData\Client;

class Web
{
    private static $global;

    private static function globalClient()
    {
        if (!self::$global) {
            self::$global = new Client();
        }
        return self::$global;
    }

    /**
     * @param int $client_id
     * @param string $message
     * @return bool|mixed
     */
    public static function onMessage($client_id = 0, $message = '')
    {
        $message = json_decode($message, true);
        if (method_exists(self::class, $message['do'])) {
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
            $session = self::getSessionByUid($message['pile']);
            if ($session['gunInfo'][$message['gun']]['workStatus'] != 6) {
                if ($session['gunInfo'][$message['gun']]['linkStatus']) {
                    $money = self::globalClient()->hGetField('UserInfo', $message['uid'], 'money') ?: 0;
                    if ($money > 5) {
                        $session['orderInfo'][$message['gun']] = $message['orderNo'];
                        $session['userInfo'][$message['gun']] = $message['uid'];
                        self::setSessionByUid($message['pile'], $session);
                        self::globalClient()->hSetField('PileInfo', $message['pile'], 'orderInfo', json_encode($_SESSION['orderInfo']));
                        self::globalClient()->hSetField('PileInfo', $message['pile'], 'userInfo', json_encode($_SESSION['userInfo']));
                        Gateway::joinGroup($client_id, $message['orderNo']);
                        Gateway::sendToUid($message['pile'], ['cmd' => 7, 'gun' => $message['gun'], 'orderNo' => $message['orderNo']]);
                        return Gateway::sendToClient($client_id, json_encode(['code' => 204]));
                    }
                    return Gateway::sendToClient($client_id, json_encode(['code' => 201]));
                }
                return Gateway::sendToClient($client_id, json_encode(['code' => 202]));
            }
            return Gateway::sendToClient($client_id, json_encode(['code' => 203]));
        }
        return Gateway::sendToClient($client_id, json_encode(['code' => 200]));
    }

    /**
     * 查看充电
     * @param $client_id
     * @param $message
     */
    private static function seeCharge($client_id, $message)
    {
        Gateway::joinGroup($client_id, $message['orderNo']);
        if ($order = self::globalClient()->hGet('ChargeOrder', $message['orderNo'])) {
            $order['rule'] = TldPile::getRule($order['pile']);
            if ($order['status'] == 1) {
                Gateway::sendToClient($client_id, json_encode(['code' => 205, 'data' => $order]));
                return;
            }
            if ($order['status'] == 2) {
                Gateway::sendToClient($client_id, json_encode(['code' => 206, 'data' => $order]));
                return;
            }
            if ($order['status'] == 3) {
                Gateway::sendToClient($client_id, json_encode(['code' => 208, 'data' => $order]));
                return;
            }
        }
        Gateway::sendToClient($client_id, json_encode(['code' => 209, 'data' => $order]));
        return;
    }


    /**
     * 结束充电
     * @param $client_id
     * @param $message
     */
    private static function endCharge($client_id, $message)
    {
        if (Gateway::isUidOnline($message['pile'])) {
            $session = self::getSessionByUid($message['pile']);
            $orderNo = isset($session['orderInfo'][$message['gun']]) ? $session['orderInfo'][$message['gun']] : '';
            if ($orderNo) {
                Gateway::joinGroup($client_id, $orderNo);
                Gateway::sendToUid($message['pile'], ['cmd' => 5, 'gun' => $message['gun'], 'code' => 2, 'val' => 85]);
                return;
            }
        }
        Gateway::sendToClient($client_id, json_encode(['code' => 301]));
        return;
    }

    /**
     * 查询在线电桩
     * @param $client_id
     * @param $message
     */
    private static function pileList($client_id, $message)
    {
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
            Gateway::sendToClient($client_id, json_encode(['code' => 600, 'data' => self::getSessionByUid($message['pile'])]));
            return;
        }
        Gateway::sendToClient($client_id, json_encode(['code' => 601]));
        return;
    }

    /**
     * 根据uid获取session
     * @param string $uid
     * @return mixed
     */
    private static function getSessionByUid($uid = '')
    {
        $client_ids = Gateway::getClientIdByUid($uid);
        return Gateway::getSession($client_ids[0]);
    }

    /**
     * @param string $uid
     * @param array $session
     */
    private static function setSessionByUid($uid = '', $session = [])
    {
        $client_ids = Gateway::getClientIdByUid($uid);
        Gateway::setSession($client_ids[0], $session);
    }
}