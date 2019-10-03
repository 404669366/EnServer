<?php

namespace startup;

use GatewayWorker\Lib\Gateway;

class Events
{
    public static function onMessage($client_id, $data)
    {
        switch ($_SERVER['GATEWAY_PORT']) {
            //todo 客户端
            case 20001:
                $data = json_decode($data, true);
                switch ($data['do']) {
                    case 'beginCharge':
                        if (!Gateway::isUidOnline($data['pile'])) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 200]));
                            break;
                        }
                        $gun = self::globalClient()->hGet('GunInfo', $data['pile'] . $data['gun']);
                        if ($gun['workStatus']) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 203]));
                            break;
                        }
                        if (!$gun['linkStatus']) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 202]));
                            break;
                        }
                        $gun['orderNo'] = $data['orderNo'];
                        $gun['user_id'] = $data['user_id'];
                        self::globalClient()->hSet('GunInfo', $data['pile'] . $data['gun'], $gun);
                        Gateway::sendToUid($data['pile'], ['cmd' => 7, 'gun' => $data['gun'], 'orderNo' => $data['orderNo']]);
                        Gateway::joinGroup($client_id, $data['orderNo']);
                        Gateway::sendToClient($client_id, json_encode(['code' => 204]));
                        break;
                    case 'seeCharge':
                        Gateway::joinGroup($client_id, $data['orderNo']);
                        if ($order = self::globalClient()->hGet('ChargeOrder', $data['orderNo'])) {
                            $order['rule'] = self::getRule($order['pile']);
                            if ($order['status'] == 1) {
                                Gateway::sendToClient($client_id, json_encode(['code' => 205, 'data' => $order]));
                                break;
                            }
                            if ($order['status'] == 2) {
                                Gateway::sendToClient($client_id, json_encode(['code' => 206, 'data' => $order]));
                                break;
                            }
                            if ($order['status'] == 3) {
                                Gateway::sendToClient($client_id, json_encode(['code' => 208, 'data' => $order]));
                                break;
                            }
                        }
                        Gateway::sendToClient($client_id, json_encode(['code' => 209, 'data' => $order]));
                        break;
                    case 'endCharge':
                        if (Gateway::isUidOnline($data['pile'])) {
                            $orderNo = self::globalClient()->hGetField('GunInfo', $data['pile'] . $data['gun'], 'orderNo');
                            Gateway::joinGroup($client_id, $orderNo);
                            Gateway::sendToUid($data['pile'], ['cmd' => 5, 'gun' => $data['gun'], 'code' => 2, 'val' => 85]);
                            break;
                        }
                        Gateway::sendToClient($client_id, json_encode(['code' => 301]));
                        break;
                    case 'pileList':
                        Gateway::sendToClient($client_id, json_encode(['code' => 500, 'data' => Gateway::getAllUidList()]));
                        break;
                    case 'pileInfo':
                        if (Gateway::isUidOnline($data['pile'])) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 600, 'data' => self::getSessionByUid($data['pile'])]));
                            break;
                        }
                        Gateway::sendToClient($client_id, json_encode(['code' => 601]));
                        break;
                    default:
                        Gateway::sendToClient($client_id, json_encode(['code' => 100]));
                        break;
                }
                break;
            //todo 特来电电桩
            case 20002:
                switch ($data['cmd']) {
                    case 62:
                        $orderNo = self::globalClient()->hGetField('GunInfo', $data['no'] . $data['gun'], 'orderNo');
                        if ($data['result'] == 0) {
                            Gateway::sendToGroup($orderNo, json_encode(['code' => 300]));
                            break;
                        }
                        Gateway::sendToGroup($orderNo, json_encode(['code' => 301]));
                        break;
                    case 102:
                        Gateway::sendToClient($client_id, ['cmd' => 101, 'times' => $data['heartNo'] + 1]);
                        break;
                    case 104:
                        if ($data['gun'] == 8) {
                            var_dump(['gun' => $data['gun'], 'work' => $data['workStatus'], 'link' => $data['linkStatus']]);
                        }
                        Gateway::bindUid($client_id, $data['no']);
                        $gun = self::globalClient()->hGet('GunInfo', $data['no'] . $data['gun']) ?: [];
                        if (isset($gun['orderNo'])) {
                            if ($gun['workStatus'] == 1 && in_array($data['workStatus'], [3, 4, 6])) {
                                Gateway::sendToGroup($gun['orderNo'], json_encode(['code' => 200]));
                            }
                            if (in_array($gun['workStatus'], [0, 1, 2]) && $data['workStatus'] == 2) {
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
                                $order = self::globalClient()->hGet('ChargeOrder', $gun['orderNo']) ?: $order;
                                $order['soc'] = $data['soc'];
                                $order['power'] = round($data['power'] / 10, 2);
                                $order['duration'] = $data['duration'];
                                $data['electricQuantity'] = round($data['electricQuantity'] / 100, 2);
                                $order['electricQuantity'] += $data['electricQuantity'];
                                $order['basisMoney'] += round($rule[2] * $data['electricQuantity'], 2);
                                $order['serviceMoney'] += round($rule[3] * $data['electricQuantity'], 2);
                                self::globalClient()->hSet('ChargeOrder', $gun['orderNo'], $order);
                                $code = 205;
                                $userMoney = self::globalClient()->hGetField('UserInfo', $gun['user_id'], 'money') ?: 0;
                                if (($order['basisMoney'] + $order['serviceMoney'] + 5) >= $userMoney) {
                                    $code = 207;
                                    Gateway::sendToClient($client_id, ['cmd' => 5, 'gun' => $data['gun'], 'code' => 2, 'val' => 85]);
                                }
                                Gateway::sendToGroup($order['no'], json_encode(['code' => $code, 'data' => $order]));
                            }
                            if ($gun['workStatus'] == 2 && in_array($data['workStatus'], [0, 3, 6])) {
                                $rule = self::getRule($data['no']);
                                $order = self::globalClient()->hGet('ChargeOrder', $gun['orderNo']);
                                $order['status'] = 2;
                                $order['soc'] = $data['soc'];
                                $order['power'] = round($data['power'] / 10, 2);
                                $order['duration'] = $data['duration'];
                                $order['rule'] = $rule;
                                $data['electricQuantity'] = round($data['electricQuantity'] / 100, 2);
                                $order['electricQuantity'] += $data['electricQuantity'];
                                $order['basisMoney'] += round($rule[2] * $data['electricQuantity'], 2);
                                $order['serviceMoney'] += round($rule[3] * $data['electricQuantity'], 2);
                                self::globalClient()->hSet('ChargeOrder', $gun['orderNo'], $order);
                                Gateway::sendToGroup($order['no'], json_encode(['code' => 206, 'data' => $order]));
                            }
                        }
                        $gun['workStatus'] = $data['workStatus'];
                        $gun['linkStatus'] = $data['linkStatus'];
                        self::globalClient()->hSet('GunInfo', $data['no'] . $data['gun'], $gun);
                        Gateway::sendToClient($client_id, ['cmd' => 103, 'gun' => $data['gun']]);
                        break;
                    case 106:
                        $_SESSION['no'] = $data['no'];
                        $_SESSION['gunCount'] = $data['gunCount'];
                        Gateway::sendToClient($client_id, ['cmd' => 105, 'random' => $data['random']]);
                        Gateway::sendToClient($client_id, ['cmd' => 3, 'type' => 1, 'code' => 2, 'val' => self::getTime()]);
                        break;
                    case 108:
                        self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'alarmInfo', $data['alarmInfo']);
                        break;
                    case 110:
                        Gateway::sendToClient($client_id, ['cmd' => 109]);
                        break;
                    case 202:
                        var_dump($data['orderNo']);
                        if ($order = self::globalClient()->hGet('ChargeOrder', $data['orderNo'])) {
                            var_dump($order);
                            $rule = self::getRule($data['no']);
                            $order['status'] = 3;
                            $order['created_at'] = $data['beginTime'];
                            $order['soc'] = $data['endSoc'];
                            $order['power'] = 0;
                            $order['duration'] = $data['duration'];
                            $order['rule'] = $rule;
                            $data['electricQuantity'] = round($data['electricQuantity'] / 100, 2);
                            $order['electricQuantity'] += $data['electricQuantity'];
                            $order['basisMoney'] += round($rule[2] * $data['electricQuantity'], 2);
                            $order['serviceMoney'] += round($rule[3] * $data['electricQuantity'], 2);
                            self::globalClient()->hSet('ChargeOrder', $data['orderNo'], $order);
                            Gateway::sendToGroup($data['orderNo'], json_encode(['code' => 208, 'data' => $order]));
                        }
                        Gateway::sendToClient($client_id, ['cmd' => 201, 'gun' => $data['gun'], 'cardNo' => $data['cardNo'], 'index' => $data['index']]);
                        break;
                }
                break;
        }
    }

    public static function onClose($client_id)
    {
        switch ($_SERVER['GATEWAY_PORT']) {
            //todo 特来电电桩
            case 20002:
                for ($i = 1; $i <= $_SESSION['gunCount']; $i++) {
                    if ($orderNo = self::globalClient()->hGetField('GunInfo', $_SESSION['no'] . $i, 'orderNo')) {
                        self::globalClient()->hSetField('ChargeOrder', $orderNo, 'status', 3);
                        Gateway::sendToGroup($orderNo, json_encode(['code' => 208]));
                    }
                }
        }
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
     * @param mixed $session
     */
    private static function setSessionByUid($uid = '', $session = [])
    {
        $client_ids = Gateway::getClientIdByUid($uid);
        Gateway::setSession($client_ids[0], $session);
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

    private static $global;

    private static function globalClient()
    {
        if (!self::$global) {
            self::$global = new \GlobalData\Client();
        }
        return self::$global;
    }
}