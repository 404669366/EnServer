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
                $data = json_encode($data, true);
                switch ($data['do']) {
                    case 'beginCharge':
                        if (!Gateway::isUidOnline($data['pile'])) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 200]));
                            break;
                        }
                        $session = self::getSessionByUid($data['pile']);
                        if ($session['gunInfo'][$data['gun']]['workStatus']) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 203]));
                            break;
                        }
                        if (!$session['gunInfo'][$data['gun']]['linkStatus']) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 202]));
                            break;
                        }
                        $money = self::globalClient()->hGetField('UserInfo', $data['uid'], 'money') ?: 0;
                        if ($money < 5) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 201]));
                            break;
                        }
                        $session['orderInfo'][$data['gun']] = $data['orderNo'];
                        $session['userInfo'][$data['gun']] = $data['uid'];
                        self::setSessionByUid($data['pile'], $session);
                        self::globalClient()->hSetField('PileInfo', $data['pile'], 'orderInfo', json_encode($_SESSION['orderInfo']));
                        self::globalClient()->hSetField('PileInfo', $data['pile'], 'userInfo', json_encode($_SESSION['userInfo']));
                        Gateway::joinGroup($client_id, $data['orderNo']);
                        Gateway::sendToUid($data['pile'], ['cmd' => 7, 'gun' => $data['gun'], 'orderNo' => $data['orderNo']]);
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
                            $session = self::getSessionByUid($data['pile']);
                            $orderNo = isset($session['orderInfo'][$data['gun']]) ? $session['orderInfo'][$data['gun']] : '';
                            if ($orderNo) {
                                Gateway::joinGroup($client_id, $orderNo);
                                Gateway::sendToUid($data['pile'], ['cmd' => 5, 'gun' => $data['gun'], 'code' => 2, 'val' => 85]);
                                break;
                            }
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
                        if ($data['result'] == 0) {
                            Gateway::sendToGroup($_SESSION['orderInfo'][$data['gun']], json_encode(['code' => 300]));
                            break;
                        }
                        Gateway::sendToGroup($_SESSION['orderInfo'][$data['gun']], json_encode(['code' => 301]));
                        break;
                    case 102:
                        Gateway::sendToClient($client_id, ['cmd' => 101, 'times' => $data['heartNo'] + 1]);
                        break;
                    case 104:
                        if ($data['gun'] == 8) {
                            var_dump(['gun' => $data['gun'], 'work' => $data['workStatus'], 'link' => $data['linkStatus']]);
                        }
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
                        break;
                    case 106:
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
                        break;
                    case 108:
                        $_SESSION['alarmInfo'] = $data['alarmInfo'];
                        break;
                    case 110:
                        Gateway::sendToClient($client_id, ['cmd' => 109]);
                        break;
                    case 202:
                        if ($order = self::globalClient()->hGet('ChargeOrder', $data['orderNo'])) {
                            if ($order['status'] != 3) {
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
                foreach ($_SESSION['orderInfo'] as $v) {
                    self::globalClient()->hSetField('ChargeOrder', $v, 'status', 3);
                    Gateway::sendToGroup($v, json_encode(['code' => 208]));
                }
                self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'orderInfo', '{}');
                self::globalClient()->hSetField('PileInfo', $_SESSION['no'], 'userInfo', '{}');
                break;
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