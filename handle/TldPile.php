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
        var_dump($data['cmd']);
        if ($data && method_exists(self::class, 'cmd_' . $data['cmd'])) {
            call_user_func_array('self::cmd_' . $data['cmd'], [$client_id, $data]);
        }
    }

    /**
     * @param $client_id
     */
    public static function onClose($client_id)
    {
        for ($i = 1; $i <= $_SESSION['gunCount']; $i++) {
            if ($orderNo = self::globalClient()->hGetField('GunInfo', $_SESSION['no'] . '-' . $i, 'orderNo')) {
                self::globalClient()->hSetField('ChargeOrder', $orderNo, 'status', 3);
                Gateway::sendToGroup($orderNo, json_encode(['code' => 101]));
            }
        }
    }

    private static function cmd_62($client_id, $data)
    {
        $gun = self::globalClient()->hGet('GunInfo', $data['no'] . '-' . $data['gun']);
        if ($data['result'] == 0) {
            Gateway::sendToGroup($gun['orderNo'], json_encode(['code' => 300]));
        } else {
            Gateway::sendToGroup($gun['orderNo'], json_encode(['code' => 301]));
        }
    }

    private static function cmd_8($client_id, $data)
    {
        if ($data['result']) {
            self::globalClient()->hSetField('GunInfo', $data['no'] . '-' . $data['gun'], 'orderNo', $data['orderNo']);
        } else {
            Gateway::sendToGroup($data['orderNo'], json_encode(['code' => 200]));
        }
    }

    private static function cmd_102($client_id, $data)
    {
        $times = $data['heartNo'] + 1;
        Gateway::sendToClient($client_id, ['cmd' => 101, 'params' => [$times]]);
    }

    private static function cmd_104($client_id, $data)
    {
        $gun = self::globalClient()->hGet('GunInfo', $data['no'] . '-' . $data['gun']) ?: ['workStatus' => $data['workStatus'], 'linkStatus' => $data['linkStatus'], 'orderNo' => '', 'user_id' => 0];
        if ($gun['workStatus'] == 1 && in_array($data['workStatus'], [0, 3, 4, 6])) {
            Gateway::sendToGroup($gun['orderNo'], json_encode(['code' => 200]));
            $gun['orderNo'] = '';
            $gun['user_id'] = 0;
        }
        if (in_array($gun['workStatus'], [0, 1, 2]) && $data['workStatus'] == 2) {
            $userMoney = self::globalClient()->hGetField('UserInfo', $gun['uid'], 'money') ?: 0;
            $rule = self::getRule($data['no']);
            $order = [
                'no' => $gun['orderNo'],
                'uid' => $gun['user_id'],
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
            $order = self::globalClient()->hGet('ChargeOrder', $order['no']) ?: $order;
            $order['soc'] = $data['soc'];
            $order['power'] = $data['power'] / 10;
            $order['duration'] = $data['duration'];
            $data['electricQuantity'] = $data['electricQuantity'] / 100;
            $order['electricQuantity'] += $data['electricQuantity'];
            $order['basisMoney'] += $rule[2] * $data['electricQuantity'];
            $order['serviceMoney'] += $rule[3] * $data['electricQuantity'];
            self::globalClient()->hSet('ChargeOrder', $order['no'], $order);
            $code = 205;
            if (($order['basisMoney'] + $order['serviceMoney'] + 5) >= $userMoney) {
                Gateway::sendToClient($client_id, ['cmd' => 5, 'params' => [$data['gun'], 2, 85]]);
                $code = 207;
            }
            Gateway::sendToGroup($order['no'], json_encode(['code' => $code, 'data' => $order]));
        }

        if ($gun['workStatus'] == 2 && in_array($data['workStatus'], [3, 6])) {
            $rule = self::getRule($data['no']);
            $order = self::globalClient()->hGet('ChargeOrder', $gun['orderNo']);
            $order['status'] = 2;
            $order['soc'] = $data['soc'];
            $order['power'] = $data['power'] / 10;
            $order['duration'] = $data['duration'];
            $order['rule'] = $rule;
            $data['electricQuantity'] = $data['electricQuantity'] / 100;
            $order['electricQuantity'] += $data['electricQuantity'];
            $order['basisMoney'] += $rule[2] * $data['electricQuantity'];
            $order['serviceMoney'] += $rule[3] * $data['electricQuantity'];
            self::globalClient()->hSet('ChargeOrder', $order['no'], $order);
            Gateway::sendToGroup($order['no'], json_encode(['code' => 206, 'data' => $order]));
        }

        $gun['workStatus'] = $data['workStatus'];
        $gun['linkStatus'] = $data['linkStatus'];
        self::globalClient()->hSet('GunInfo', $data['no'] . '-' . $data['gun'], $gun);
        Gateway::sendToClient($client_id, ['cmd' => 103, 'params' => [$data['gun']]]);
    }

    private static function cmd_106($client_id, $data)
    {
        $_SESSION['no'] = $data['no'];
        $_SESSION['gunCount'] = $data['gunCount'];
        self::globalClient()->hSetField('PileInfo', $data['no'], 'client_id', $client_id);
        self::globalClient()->hSetField('PileInfo', $data['no'], 'gunCount', $data['gunCount']);
        Gateway::bindUid($client_id, $data['no']);
        Gateway::sendToClient($client_id, ['cmd' => 105, 'params' => [$data['random']]]);
        Gateway::sendToClient($client_id, ['cmd' => 3, 'params' => [1, 2, self::getTime()]]);
    }

    private static function cmd_108($client_id, $data)
    {
        self::globalClient()->hSetField('PileInfo', $data['no'], 'alarmInfo', $data['alarmInfo']);
    }

    private static function cmd_110($client_id, $data)
    {
        Gateway::sendToClient($client_id, ['cmd' => 109, 'params' => []]);
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
        self::globalClient()->hSet('GunInfo', $data['no'] . '-' . $data['gun'], ['workStatus' => 0, 'linkStatus' => 0, 'orderNo' => '', 'user_id' => 0]);
        Gateway::sendToGroup($data['orderNo'], json_encode(['code' => 208, 'data' => $order]));
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