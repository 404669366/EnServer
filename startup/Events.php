<?php

namespace startup;

use GatewayWorker\Lib\Gateway;
use GlobalData\Client;
use Workerman\MySQL\Connection;

class Events
{
    /**
     * @var Connection
     */
    private static $db;

    public static function onWorkerStart()
    {
        self::$db = new Connection('127.0.0.1', '3306', 'root', 'fi9^BRLHschX%V96', 'en');
    }

    public static function onMessage($client_id, $data)
    {
        switch ($_SERVER['GATEWAY_PORT']) {
            //todo 客户端
            case 20001:
                $data = json_decode($data, true);
                Gateway::sendToGroup('server', json_encode(['msg' => $data, 'time' => date('Y-m-d H:i:s')]));
                switch ($data['do']) {
                    case 'beginCharge':
                        if (!Gateway::isUidOnline($data['pile'])) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 100]));
                            self::$db->update('en_order')->cols(['status' => 4])->where("no='{$data['orderNo']}'")->query();
                            break;
                        }
                        $status = self::getSessionByUid($data['pile'])['status'][$data['gun']];
                        if ($status['workStatus']) {
                            self::$db->update('en_order')->cols(['status' => 4])->where("no='{$data['orderNo']}'")->query();
                            Gateway::sendToClient($client_id, json_encode(['code' => 203]));
                            break;
                        }
                        if (!$status['linkStatus']) {
                            self::$db->update('en_order')->cols(['status' => 4])->where("no='{$data['orderNo']}'")->query();
                            Gateway::sendToClient($client_id, json_encode(['code' => 202]));
                            break;
                        }
                        Gateway::sendToUid($data['pile'], ['cmd' => 7, 'gun' => $data['gun'], 'orderNo' => $data['orderNo']]);
                        Gateway::joinGroup($client_id, $data['pile'] . $data['gun']);
                        Gateway::sendToClient($client_id, json_encode(['code' => 204]));
                        break;
                    case 'seeCharge':
                        Gateway::joinGroup($client_id, $data['pile'] . $data['gun']);
                        break;
                    case 'endCharge':
                        if (Gateway::isUidOnline($data['pile'])) {
                            Gateway::joinGroup($client_id, $data['pile'] . $data['gun']);
                            Gateway::sendToUid($data['pile'], ['cmd' => 5, 'gun' => $data['gun'], 'code' => 2, 'val' => 85]);
                            break;
                        }
                        Gateway::sendToClient($client_id, json_encode(['code' => 301]));
                        break;
                    case 'seePile':
                        Gateway::joinGroup($client_id, $data['pile']);
                        if (Gateway::isUidOnline($data['pile'])) {
                            Gateway::sendToClient($client_id, json_encode(['code' => 600, 'data' => self::getSessionByUid($data['pile'])]));
                            break;
                        }
                        Gateway::sendToClient($client_id, json_encode(['code' => 601]));
                        break;
                    case 'joinServer':
                        Gateway::joinGroup($client_id, 'server');
                        break;
                    case 'leaveServer':
                        Gateway::leaveGroup($client_id, 'server');
                        break;
                    default:
                        Gateway::sendToClient($client_id, json_encode(['code' => 100]));
                        break;
                }
                break;
            //todo 特来电电桩
            case 20002:
                Gateway::sendToGroup('server', json_encode(['msg' => $data, 'time' => date('Y-m-d H:i:s')]));
                switch ($data['cmd']) {
                    case 62:
                        if ($data['result'] == 0) {
                            Gateway::sendToGroup($data['no'] . $data['gun'], json_encode(['code' => 300]));
                            break;
                        }
                        Gateway::sendToGroup($data['no'] . $data['gun'], json_encode(['code' => 301]));
                        break;
                    case 102:
                        Gateway::sendToClient($client_id, ['cmd' => 101, 'times' => $data['heartNo'] + 1]);
                        break;
                    case 104:
                        Gateway::bindUid($client_id, $data['no']);
                        if ($data['workStatus'] == 2 && $data['linkStatus']) {
                            if ($order = self::$db->select('*')->from('en_order')->where("pile='{$data['no']}' AND gun='{$data['gun']}' AND status in(0,1)")->row()) {
                                $code = 205;
                                $rule = self::getRule($order['rules']);
                                if ($data['e'] > $order['e']) {
                                    $order['status'] = 1;
                                    $order['duration'] = $data['duration'];
                                    $e = $data['e'] - $order['e'];
                                    $order['bm'] += $rule[2] * $e;
                                    $order['sm'] += $rule[3] * $e;
                                    $order['e'] = $data['e'];
                                    self::$db->update('en_order')->cols($order)->where("no='{$order['no']}'")->query();
                                    $userMoney = self::$db->select('money')->from('en_user')->where("id={$order['uid']}")->row()['money'];
                                    if (($order['bm'] + $order['sm']) >= ($userMoney - 5)) {
                                        $code = 207;
                                        Gateway::sendToClient($client_id, ['cmd' => 5, 'gun' => $data['gun'], 'code' => 2, 'val' => 85]);
                                    }
                                }
                                $order['rule'] = $rule;
                                $order['vin'] = $data['vin'];
                                $order['soc'] = $data['soc'];
                                $order['power'] = round($data['power'] / 10, 2);
                                Gateway::sendToGroup($data['no'] . $data['gun'], json_encode(['code' => $code, 'data' => $order]));
                            }
                        }
                        if ($data['workStatus'] == 3 && $data['linkStatus']) {
                            if ($order = self::$db->select('*')->from('en_order')->where("pile='{$data['no']}' AND gun='{$data['gun']}' AND status in(0,1)")->row()) {
                                $rule = self::getRule($order['rules']);
                                if ($data['e'] > $order['e']) {
                                    $order['duration'] = $data['duration'];
                                    $e = $data['e'] - $order['e'];
                                    $order['bm'] += $rule[2] * $e;
                                    $order['sm'] += $rule[3] * $e;
                                    $order['e'] = $data['e'];
                                    self::$db->update('en_order')->cols($order)->where("no='{$order['no']}'")->query();
                                }
                                $order['rule'] = $rule;
                                $order['vin'] = $data['vin'];
                                $order['soc'] = $data['soc'];
                                $order['power'] = round($data['power'] / 10, 2);
                                Gateway::sendToGroup($data['no'] . $data['gun'], json_encode(['code' => 206, 'data' => $order]));
                            }
                        }
                        if ($data['workStatus'] == 4 && $data['linkStatus']) {
                            if ($order = self::$db->select('*')->from('en_order')->where("pile='{$data['no']}' AND gun='{$data['gun']}' AND status in(0,1)")->row()) {
                                self::$db->update('en_order')->cols(['status' => 4])->where("no='{$order['no']}'")->query();
                                Gateway::sendToGroup($data['no'] . $data['gun'], json_encode(['code' => 200]));
                            }
                        }
                        if ($data['workStatus'] == 6 && $data['linkStatus']) {
                            if ($order = self::$db->select('*')->from('en_order')->where("pile='{$data['no']}' AND gun='{$data['gun']}' AND status in(0,1)")->row()) {
                                $rule = self::getRule($order['rules']);
                                if ($data['e'] > $order['e']) {
                                    $order['duration'] = $data['duration'];
                                    $e = $data['e'] - $order['e'];
                                    $order['bm'] += $rule[2] * $e;
                                    $order['sm'] += $rule[3] * $e;
                                    $order['e'] = $data['e'];
                                }
                                $order['status'] = 2;
                                self::$db->update('en_order')->cols($order)->where("no='{$order['no']}'")->query();
                                $order['rule'] = $rule;
                                $order['vin'] = $data['vin'];
                                $order['soc'] = $data['soc'];
                                $order['power'] = round($data['power'] / 10, 2);
                                Gateway::sendToGroup($data['no'] . $data['gun'], json_encode(['code' => 208, 'data' => $order]));
                                Gateway::sendToClient($client_id, ['cmd' => 5, 'gun' => $data['gun'], 'code' => 2, 'val' => 85]);
                            }
                        }
                        $_SESSION['status'][$data['gun']] = ['workStatus' => $data['workStatus'], 'linkStatus' => $data['linkStatus']];
                        Gateway::sendToClient($client_id, ['cmd' => 103, 'gun' => $data['gun']]);
                        break;
                    case 106:
                        $_SESSION['no'] = $data['no'];
                        $_SESSION['count'] = $data['count'];
                        $order = self::$db->select('no')->from('en_pile')->where("no='{$data['no']}'")->row();
                        if ($order) {
                            self::$db->update('en_pile')->cols(['online' => 1, 'count' => $data['count']])->where("no='{$data['no']}'")->query();
                        } else {
                            self::$db->insert('en_pile')->cols(['no' => $data['no'], 'online' => 1, 'bind' => 0, 'count' => $data['count']])->query();
                        }
                        Gateway::sendToClient($client_id, ['cmd' => 105, 'random' => $data['random']]);
                        Gateway::sendToClient($client_id, ['cmd' => 3, 'type' => 1, 'code' => 2, 'val' => self::getTime()]);
                        break;
                    case 108:
                        break;
                    case 110:
                        Gateway::sendToClient($client_id, ['cmd' => 109]);
                        break;
                    case 202:
                        if ($order = self::$db->select('*')->from('en_order')->where("no='{$data['orderNo']}' AND status in(0,1)")->row()) {
                            $order['status'] = 2;
                            $order['duration'] = $data['duration'];
                            if ($data['e'] > $order['e']) {
                                $rule = self::getRule($order['rules']);
                                $e = $data['e'] - $order['e'];
                                $order['bm'] += $rule[2] * $e;
                                $order['sm'] += $rule[3] * $e;
                                $order['e'] = $data['e'];
                            }
                            self::$db->update('en_order')->cols($order)->where("no='{$data['orderNo']}'")->query();
                            Gateway::sendToGroup($data['no'] . $data['gun'], json_encode(['code' => 209]));
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
                self::$db->update('en_pile')->cols(['online' => 0])->where("no='{$_SESSION['no']}'")->query();
                self::$db->update('en_order')->cols(['status' => 2])->where("pile='{$_SESSION['no']}' AND status in(0,1)")->query();
                for ($i = 1; $i <= $_SESSION['count']; $i++) {
                    Gateway::sendToGroup($_SESSION['no'] . $i, json_encode(['code' => 209]));
                }
        }
    }

    public static function onWorkerStop($businessWorker)
    {
        self::$db->update('en_pile')->cols(['online' => 0])->query();
        self::$db->update('en_order')->cols(['status' => 2])->where("status in(0,1)")->query();
        Gateway::sendToAll(json_encode(['code' => 209]));
    }

    /**
     * 根据uid获取session
     * @param string $uid
     * @return mixed
     */
    private static function getSessionByUid($uid = '')
    {
        $client_ids = Gateway::getClientIdByUid($uid);
        return Gateway::getSession(array_shift($client_ids));
    }

    /**
     * 电桩获取当前计价规则
     * @param string $rules
     * @return array
     */
    public static function getRule($rules = '')
    {
        $now = time() - strtotime(date('Y-m-d'));
        $rules = json_decode($rules, true);
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