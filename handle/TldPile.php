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
        foreach ($_SESSION['orderInfo'] as $v) {
            self::globalClient()->hSetField('ChargeOrder', $v, 'status', 3);
            Gateway::sendToGroup($v, json_encode(['code' => 208]));
        }
        self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'orderInfo', '{}');
        self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'userInfo', '{}');
    }

    private static function cmd_62($client_id, $data)
    {
        if ($data['result'] == 0) {
            Gateway::sendToGroup($_SESSION['orderInfo'][$data['gun']], json_encode(['code' => 300]));
        } else {
            Gateway::sendToGroup($_SESSION['orderInfo'][$data['gun']], json_encode(['code' => 301]));
        }
    }

    private static function cmd_102($client_id, $data)
    {
        $times = $data['heartNo'] + 1;
        Gateway::sendToClient($client_id, ['cmd' => 101, 'times' => $times]);
    }

    private static function cmd_104($client_id, $data)
    {
        /*if (in_array($data['gun'], [6, 7, 8])) {
            echo "---------------------------104--------------------------------\r\n";
            var_dump([
                'gun' => $data['gun'],
                'workStatus' => $data['workStatus'],
                'linkStatus' => $data['linkStatus'],
            ]);
        }*/
        Gateway::bindUid($client_id, $data['no']);
        $orderNo = isset($_SESSION['orderInfo'][$data['gun']]) ? $_SESSION['orderInfo'][$data['gun']] : '';
        if ($orderNo) {
            $gun = $_SESSION['gunInfo'][$data['gun']];
            $user_id = $_SESSION['userInfo'][$data['gun']];
            if ($gun['workStatus'] == 1 && in_array($data['workStatus'], [0, 3, 4, 6])) {
                Gateway::sendToGroup($gun['orderNo'], json_encode(['code' => 200]));
                unset($_SESSION['orderInfo'][$data['gun']]);
                unset($_SESSION['userInfo'][$data['gun']]);
                self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'orderInfo', json_encode($_SESSION['orderInfo']));
                self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'userInfo', json_encode($_SESSION['userInfo']));
            }
            if (in_array($gun['workStatus'], [0, 1, 2]) && $data['workStatus'] == 2) {
                $rule = self::getRule($data['no']);
                $order = [
                    'no' => $orderNo,
                    'uid' => $user_id,
                    'pile' => $data['no'],
                    'gun' => $data['gun'],
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
                $userMoney = self::globalClient()->hGetField('UserInfo', $user_id, 'money') ?: 0;
                if (($order['basisMoney'] + $order['serviceMoney'] + 5) >= $userMoney) {
                    $code = 207;
                    Gateway::sendToClient($client_id, ['cmd' => 5, 'gun' => $data['gun'], 'code' => 2, 'val' => 85]);
                }
                Gateway::sendToGroup($order['no'], json_encode(['code' => $code, 'data' => $order]));
            }
            if ($gun['workStatus'] == 2 && in_array($data['workStatus'], [3, 6])) {
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
                Gateway::sendToGroup($order['no'], json_encode(['code' => 206, 'data' => $order]));
            }
        }
        $_SESSION['gunInfo'][$data['gun']] = ['workStatus' => $data['workStatus'], 'linkStatus' => $data['linkStatus']];
        Gateway::sendToClient($client_id, ['cmd' => 103, 'gun' => $data['gun']]);
    }

    private static function cmd_106($client_id, $data)
    {
        $_SESSION['no'] = $data['no'];
        $_SESSION['gunCount'] = $data['gunCount'];
        $pileInfo = self::globalClient()->hGet('PileInfo', $data['no']);
        if (isset($pileInfo['orderInfo']) && $pileInfo['orderInfo']) {
            $_SESSION['orderInfo'] = json_decode($pileInfo['orderInfo'], true);
        }
        if (isset($pileInfo['userInfo']) && $pileInfo['userInfo']) {
            $_SESSION['userInfo'] = json_decode($pileInfo['userInfo'], true);
        }
        self::globalClient()->hSetField('PileInfo', $data['no'], 'count', $data['gunCount']);
        Gateway::sendToClient($client_id, ['cmd' => 105, 'random' => $data['random']]);
        Gateway::sendToClient($client_id, ['cmd' => 3, 'type' => 1, 'code' => 2, 'val' => self::getTime()]);
    }

    private static function cmd_108($client_id, $data)
    {
        $_SESSION['alarmInfo'] = $data['alarmInfo'];
    }

    private static function cmd_110($client_id, $data)
    {
        Gateway::sendToClient($client_id, ['cmd' => 109]);
    }

    private static function cmd_202($client_id, $data)
    {
        if (in_array($data['gun'], [6, 7, 8])) {
            echo "---------------------------202--------------------------------\r\n";
            var_dump([
                'gun' => $data['gun'],
                'orderNo' => $data['orderNo'],
            ]);
        }
        if ($order = self::globalClient()->hGet('ChargeOrder', $data['orderNo'])) {
            var_dump($order);
            $rule = self::getRule($data['no']);
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
            unset($_SESSION['orderInfo'][$data['gun']]);
            unset($_SESSION['userInfo'][$data['gun']]);
            self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'orderInfo', json_encode($_SESSION['orderInfo']));
            self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'userInfo', json_encode($_SESSION['userInfo']));
        }
        Gateway::sendToClient($client_id, ['cmd' => 201, 'gun' => $data['gun'], 'cardNo' => $data['cardNo'], 'index' => $data['index']]);
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