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

class TldPile
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
     * @param array $data
     */
    public static function onMessage($client_id = 0, $data = [])
    {
        if ($data && method_exists(self::class, 'cmd_' . $data['cmd'])) {
            call_user_func_array('self::cmd_' . $data['cmd'], [$client_id, $data]);
        }
    }

    /**
     * @param $client_id
     */
    public static function onClose($client_id)
    {
        Gateway::sendToGroup('pileList', json_encode(['code' => 500, 'data' => Gateway::getAllUidList()]));
        foreach ($_SESSION['orderNo'] as $v) {
            self::globalClient()->hSetField('ChargeOrder', $v, 'status', 3);
            Gateway::sendToGroup($v, json_encode(['code' => 101]));
        }
    }

    private static function cmd_41($client_id, $data)
    {
        if ($data['result'] == 0) {
            $data['no'] = $data['info'];
            Gateway::bindUid($client_id, $data['info']);
            Gateway::sendToGroup($data['no'] . '_setNo', json_encode(['code' => 400]));
        } else {
            Gateway::sendToGroup($data['no'] . '_setNo', json_encode(['code' => 401]));
        }
    }

    private static function cmd_62($client_id, $data)
    {
        if ($data['result'] == 0) {
            Gateway::sendToGroup($data['no'] . $data['gun'] . '_endCharge', json_encode(['code' => 300]));
        } else {
            Gateway::sendToGroup($data['no'] . $data['gun'] . '_endCharge', json_encode(['code' => 301]));
        }

    }

    private static function cmd_8($client_id, $data)
    {
        if ($data['result'] != 0) {
            Gateway::sendToGroup($data['orderNo'], json_encode(['code' => 200]));
            unset($_SESSION['orderNo'][$data['gun']]);
            unset($_SESSION['uid'][$data['gun']]);
            Gateway::sendToGroup($data['no'] . '_pileInfo', json_encode(['code' => 600, 'data' => $_SESSION]));
        }
    }

    private static function cmd_102($client_id, $data)
    {
        $times = $data['heartNo'] + 1;
        Gateway::sendToClient($client_id, ['cmd' => 101, 'params' => [$times]]);
    }

    private static function cmd_104($client_id, $data)
    {
        $orderNo = $_SESSION['orderNo'][$data['gun']];
        $uid = $_SESSION['uid'][$data['gun']];

        if ($_SESSION['workStatus'] == 1 && $data['workStatus'] != 2) {
            Gateway::sendToGroup($orderNo, json_encode(['code' => 200]));
            unset($_SESSION['orderNo'][$data['gun']]);
            unset($_SESSION['uid'][$data['gun']]);
        }

        if (in_array($_SESSION['workStatus'], [0, 1, 2]) && $data['workStatus'] == 2) {
            $userMoney = self::globalClient()->hGetField('UserInfo', $uid, 'money') ?: 0;
            $rule = self::getRule($data['no']);
            $order = [
                'no' => $orderNo,
                'pile' => $data['no'],
                'gun' => $data['gun'],
                'uid' => $uid,
                'status' => 1,
                'created_at' => time(),
                'soc' => 0,
                'power' => 0,
                'duration' => 0,
                'rule' => $rule,
                'electricQuantity' => 0,
                'basisMoney' => 0,
                'serviceMoney' => 0,
            ];
            $order = self::globalClient()->hGet('ChargeOrder', $orderNo) ?: $order;
            $order['soc'] = $data['soc'];
            $order['power'] = $data['power'] / 10;
            $order['duration'] = $data['duration'];
            $data['electricQuantity'] = $data['electricQuantity'] / 100;
            $order['electricQuantity'] += $data['electricQuantity'];
            $order['basisMoney'] += $rule[2] * $data['electricQuantity'];
            $order['serviceMoney'] += $rule[3] * $data['electricQuantity'];
            self::globalClient()->hSet('ChargeOrder', $orderNo, $order);
            $code = 205;
            if (($order['basisMoney'] + $order['serviceMoney'] + 1) >= $userMoney) {
                Gateway::sendToClient($client_id, ['cmd' => 5, 'params' => [$data['gun'], 2, 85]]);
                $code = 207;
            }
            Gateway::sendToGroup($orderNo, json_encode(['code' => $code, 'data' => $order]));
        }

        if ($_SESSION['workStatus'] == 2 && in_array($data['workStatus'], [3, 6])) {
            $rule = self::getRule($data['no']);
            $order = self::globalClient()->hGet('ChargeOrder', $orderNo);
            $order['status'] = 2;
            $order['soc'] = $data['soc'];
            $order['power'] = $data['power'] / 10;
            $order['duration'] = $data['duration'];
            $order['rule'] = $rule;
            $data['electricQuantity'] = $data['electricQuantity'] / 100;
            $order['electricQuantity'] += $data['electricQuantity'];
            $order['basisMoney'] += $rule[2] * $data['electricQuantity'];
            $order['serviceMoney'] += $rule[3] * $data['electricQuantity'];
            self::globalClient()->hSet('ChargeOrder', $orderNo, $order);
            Gateway::sendToGroup($orderNo, json_encode(['code' => 206, 'data' => $order]));
        }

        $_SESSION['carStatus'] = $data['carStatus'];
        $_SESSION['workStatus'] = $data['workStatus'];
        Gateway::sendToClient($client_id, ['cmd' => 103, 'params' => [$data['gun']]]);
        Gateway::sendToGroup($data['no'] . '_pileInfo', json_encode(['code' => 600, 'data' => $_SESSION]));
    }

    private static function cmd_106($client_id, $data)
    {
        if (!isset($_SESSION['no'])) {
            $_SESSION['gunCount'] = $data['gunCount'];
            $_SESSION['uid'] = [];
            $_SESSION['orderNo'] = [];
            $_SESSION['carStatus'] = 0;
            $_SESSION['workStatus'] = 6;
            $_SESSION['alarmInfo'] = '';
        }
        $_SESSION['no'] = $data['no'];
        Gateway::bindUid($client_id, $data['no']);
        Gateway::sendToClient($client_id, ['cmd' => 105, 'params' => [$data['random']]]);
        Gateway::sendToClient($client_id, ['cmd' => 3, 'params' => [1, 2, self::getTime()]]);
        Gateway::sendToGroup('pileList', json_encode(['code' => 500, 'data' => Gateway::getAllUidList()]));
        Gateway::sendToGroup($data['no'] . '_pileInfo', json_encode(['code' => 600, 'data' => $_SESSION]));
    }

    private static function cmd_108($client_id, $data)
    {
        $_SESSION['alarmInfo'] = $data['alarmInfo'];
        Gateway::sendToGroup($data['no'] . '_pileInfo', json_encode(['code' => 600, 'data' => $_SESSION]));
    }

    private static function cmd_110($client_id, $data)
    {
        Gateway::sendToClient($client_id, ['cmd' => 109, 'params' => []]);
        if ($data['failType'] != 0) {
            Gateway::sendToGroup($_SESSION['orderNo'][$data['gun']], json_encode(['code' => 200]));
            unset($_SESSION['orderNo'][$data['gun']]);
            unset($_SESSION['uid'][$data['gun']]);
            Gateway::sendToGroup($data['no'] . '_pileInfo', json_encode(['code' => 600, 'data' => $_SESSION]));
        }
    }

    private static function cmd_202($client_id, $data)
    {
        Gateway::sendToClient($client_id, ['cmd' => 201, 'params' => [$data['gun'], $data['cardNo'], $data['index']]]);
        $rule = self::getRule($data['no']);
        $order = self::globalClient()->hGet('ChargeOrder', $data['orderNo']);
        $order['status'] = 3;
        $order['created_at'] = $data['beginTime'];
        $order['soc'] = $data['endSoc'];
        $order['power'] = 0;
        $order['duration'] = $data['duration'];
        $order['rule'] = $rule;
        $data['electricQuantity'] = $data['electricQuantity'] / 100;
        $order['electricQuantity'] += $data['electricQuantity'];
        $order['basisMoney'] += $rule[2] * $data['electricQuantity'];
        $order['serviceMoney'] += $rule[3] * $data['electricQuantity'];
        self::globalClient()->hSet('ChargeOrder', $data['orderNo'], $order);
        Gateway::sendToGroup($data['orderNo'], json_encode(['code' => 208, 'data' => $order]));
        unset($_SESSION['orderNo'][$data['gun']]);
        unset($_SESSION['uid'][$data['gun']]);
        Gateway::sendToGroup($data['no'] . '_pileInfo', json_encode(['code' => 600, 'data' => $_SESSION]));
    }

    /**
     * 返回当前计价规则
     * @param string $no
     * @param int $time
     * @return array
     */
    public static function getRule($no = '', $time = 0)
    {
        $time = $time ?: time();
        $now = $time - strtotime(date('Y-m-d'));
        $rules = json_decode(self::globalClient()->hGetField('PileInfo', $no, 'rules'), true);
        foreach ($rules as $v) {
            if ($now >= $v[0] && $now < $v[1]) {
                return $v;
            }
        }
        return [0, 86400, 0.8, 0.6];
    }

    /**
     * 获取时间
     * @param int $timeStamp
     * @return string
     */
    public static function getTime($timeStamp = 0)
    {
        $timeStamp = $timeStamp ?: time();
        $timeArr = str_split(date('YmdHis', $timeStamp + 8 * 3600) . '00', 2);
        $timeStr = '';
        foreach ($timeArr as $v) {
            $timeStr .= pack('C', (int)$v);
        }
        return $timeStr;
    }
}