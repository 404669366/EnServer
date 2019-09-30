<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/2/28
 * Time: 12:38
 */

namespace Workerman\Protocols;

class Tld
{
    public static function input($buffer)
    {
        if (strlen($buffer) < 4) {
            return 0;
        }
        return unpack('vv', substr($buffer, 2, 2))['v'];
    }

    public static function decode($buffer)
    {
        $length = unpack('vv', substr($buffer, 2, 2))['v'];
        $checkPlus1 = self::checkPlus(substr($buffer, 6, $length - 7));
        $checkPlus2 = unpack('Cv', substr($buffer, -1))['v'];
        if ($checkPlus1 == $checkPlus2) {
            $cmd = unpack('vv', substr($buffer, 6, 2))['v'];
            $length = unpack('vv', substr($buffer, 2, 2))['v'];
            if (method_exists(self::class, 'cmd_' . $cmd)) {
                return call_user_func_array('self::cmd_' . $cmd, [substr($buffer, 8, $length - 9)]);
            }
        }
        return [];
    }

    public static function encode($data)
    {
        if (method_exists(self::class, 'cmd_' . $data['cmd'])) {
            return call_user_func_array('self::cmd_' . $data['cmd'], $data['params']);
        }
        return '';
    }

    private static function cmd_1($type, $code, $val = 0)
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        $data .= pack('C', $type);
        $data .= pack('V', $code);
        $data .= pack('C', 1);
        $data .= pack('v', 4);
        if ($type == 1) {
            $data .= pack('V', $val);
        }
        return self::composeMsg(1, $data);
    }

    private static function cmd_2($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['type'] = unpack('Cv', substr($buffer, 36, 1))['v'];
        $data['code'] = unpack('Vv', substr($buffer, 37, 4))['v'];
        $data['num'] = unpack('Cv', substr($buffer, 41, 1))['v'];
        $data['result'] = unpack('Cv', substr($buffer, 42, 1))['v'];
        $data['info'] = unpack('Vv', substr($buffer, 43, 4))['v'];
        $data['cmd'] = 2;
        return $data;
    }

    private static function cmd_3($type, $code, $val = '')
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        $data .= pack('C', $type);
        $data .= pack('V', $code);
        $data .= pack('v', strlen($val));
        $data .= $val;
        return self::composeMsg(3, $data);
    }

    private static function cmd_4($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['type'] = unpack('Cv', substr($buffer, 36, 1))['v'];
        $data['code'] = unpack('Vv', substr($buffer, 37, 4))['v'];
        $data['result'] = unpack('Cv', substr($buffer, 41, 1))['v'];
        $data['cmd'] = 4;
        return $data;
    }

    private static function cmd_5($gun, $code, $val)
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        $data .= pack('C', $gun);
        $data .= pack('V', $code);
        $data .= pack('C', 1);
        $data .= pack('v', 4);
        $data .= pack('V', $val);
        return self::composeMsg(5, $data);
    }

    private static function cmd_6($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['gun'] = unpack('Cv', substr($buffer, 36, 1))['v'];
        $data['code'] = unpack('Vv', substr($buffer, 37, 4))['v'];
        $data['result'] = unpack('Cv', substr($buffer, 42, 1))['v'];
        $data['cmd'] = 6;
        if ($data['code'] == 2) {
            $data['cmd'] = 62;
        }
        return $data;
    }

    private static function cmd_7($gun, $orderNo)
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        $data .= pack('C', $gun);
        $data .= pack('V', 0);
        $data .= pack('V', 0);
        $data .= pack('V', 0);
        $data .= pack('V', 0);
        $data .= self::getTime();
        $data .= pack('C', 0);
        $no = self::composeStr32By0($orderNo);
        $data .= $no;
        $data .= pack('C', 0);
        $data .= pack('V', 0);
        $data .= $no;
        return self::composeMsg(7, $data);
    }

    private static function cmd_8($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['gun'] = unpack('Cv', substr($buffer, 36, 1))['v'];
        $data['result'] = unpack('Cv', substr($buffer, 37, 1))['v'];
        $data['orderNo'] = unpack('a32v', substr($buffer, 38, 32))['v'];
        $data['cmd'] = 8;
        return $data;
    }

    private static function cmd_101($times)
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        $data .= pack('v', $times);
        return self::composeMsg(101, $data);
    }

    private static function cmd_102($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['heartNo'] = unpack('vv', substr($buffer, 36, 2))['v'];
        $data['gunStatus'] = self::parseBin(substr($buffer, -16, 16));
        $data['cmd'] = 102;
        return $data;
    }

    private static function cmd_103($gun)
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        $data .= pack('C', $gun);
        return self::composeMsg(103, $data);
    }

    private static function cmd_104($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['gunCount'] = unpack('Cv', substr($buffer, 36, 1))['v'];
        $data['gun'] = unpack('Cv', substr($buffer, 37, 1))['v'];
        $data['gunType'] = unpack('Cv', substr($buffer, 38, 1))['v'];
        $data['workStatus'] = unpack('Cv', substr($buffer, 39, 1))['v'];
        $data['soc'] = unpack('Cv', substr($buffer, 40, 1))['v'];
        $data['alarm'] = unpack('Vv', substr($buffer, 41, 4))['v'];
        $data['linkStatus'] = unpack('Cv', substr($buffer, 45, 1))['v'];
        $data['remainingTime'] = unpack('vv', substr($buffer, 79, 2))['v'];
        $data['duration'] = unpack('Vv', substr($buffer, 81, 4))['v'];
        $data['electricQuantity'] = unpack('Vv', substr($buffer, 85, 4))['v'];
        $data['cardNo'] = unpack('a32v', substr($buffer, 104, 32))['v'];
        $data['power'] = unpack('Vv', substr($buffer, 153, 4))['v'];
        $data['vin'] = unpack('a18v', substr($buffer, 172, 18))['v'];
        $data['cmd'] = 104;
        return $data;
    }

    private static function cmd_105($random)
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        $data .= pack('V', $random);
        $data .= pack('C', 0);
        $data .= pack('C', 0);
        $data .= pack('a', 128);
        $data .= pack('V', 0);
        $data .= pack('C', 0);
        return self::composeMsg(105, $data);
    }

    private static function cmd_106($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['sign'] = str_pad(base_convert(substr($buffer, 36, 1), 16, 2), 8, 0, STR_PAD_LEFT);
        $data['softwareEdition'] = unpack('Vv', substr($buffer, 37, 4))['v'];
        $data['project'] = unpack('vv', substr($buffer, 41, 2))['v'];
        $data['startTimes'] = unpack('Vv', substr($buffer, 43, 4))['v'];
        $data['uploadMode'] = unpack('Cv', substr($buffer, 47, 1))['v'];
        $data['checkInInterval'] = unpack('vv', substr($buffer, 48, 2))['v'];
        $data['internalVar'] = unpack('Cv', substr($buffer, 50, 1))['v'];
        $data['gunCount'] = unpack('Cv', substr($buffer, 51, 1))['v'];
        $data['reportingCycle'] = unpack('Cv', substr($buffer, 52, 1))['v'];
        $data['timeoutTimes'] = unpack('Cv', substr($buffer, 53, 1))['v'];
        $data['noteCount'] = unpack('Vv', substr($buffer, 54, 4))['v'];
        $data['time'] = self::parseTime(substr($buffer, 58, 8));
        $data['random'] = unpack('Vv', substr($buffer, 90, 4))['v'];
        $data['communicationEdition'] = unpack('vv', substr($buffer, 94, 2))['v'];
        $data['whiteListEdition'] = unpack('vv', substr($buffer, 96, 4))['v'];
        $data['cmd'] = 106;
        return $data;
    }

    private static function cmd_108($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['alarmInfo'] = self::parseBin(substr($buffer, -32, 32));
        $data['cmd'] = 108;
        return $data;
    }

    private static function cmd_109()
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        return self::composeMsg(109, $data);
    }

    private static function cmd_110($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['gun'] = unpack('Cv', substr($buffer, 36, 1))['v'];
        $data['failType'] = unpack('Vv', substr($buffer, 37, 4))['v'];
        $data['sendType'] = unpack('vv', substr($buffer, 41, 2))['v'];
        $data['vin'] = unpack('a17v', substr($buffer, 79, 17))['v'];
        $data['cmd'] = 110;
        return $data;
    }

    private static function cmd_201($gun, $cardNo, $index)
    {
        $data = pack('v', 0);
        $data .= pack('v', 0);
        $data .= pack('C', $gun);
        $data .= pack('a32', $cardNo);
        $data .= pack('V', $index);
        $data .= pack('C', 0);
        $data .= pack('V', 0);
        $data .= pack('V', 0);
        $data .= pack('V', 0);
        $data .= pack('V', 0);
        $data .= pack('V', 0);
        $data .= pack('V', 0);
        return self::composeMsg(201, $data);
    }

    private static function cmd_202($buffer)
    {
        $data = [];
        $data['no'] = trim(unpack('a32v', substr($buffer, 4, 32))['v']);
        $data['type'] = unpack('Cv', substr($buffer, 36, 1))['v'];
        $data['gun'] = unpack('Cv', substr($buffer, 37, 1))['v'];
        $data['cardNo'] = unpack('a32v', substr($buffer, 38, 32))['v'];
        $data['beginTime'] = self::parseTime(substr($buffer, 70, 8));
        $data['endTime'] = self::parseTime(substr($buffer, 78, 8));
        $data['duration'] = unpack('Vv', substr($buffer, 86, 4))['v'];
        $data['beginSoc'] = unpack('Cv', substr($buffer, 90, 1))['v'];
        $data['endSoc'] = unpack('Cv', substr($buffer, 91, 1))['v'];
        $data['endType'] = unpack('Vv', substr($buffer, 92, 4))['v'];
        $data['electricQuantity'] = unpack('Vv', substr($buffer, 96, 4))['v'];
        $data['money'] = unpack('Vv', substr($buffer, 108, 4))['v'];
        $data['index'] = unpack('Vv', substr($buffer, 112, 4))['v'];
        $data['vin'] = unpack('a17v', substr($buffer, 131, 17))['v'];
        //$data['electricInfo'] = self::parseElectricInfo(substr($buffer, 156, 96));
        $data['startType'] = unpack('Cv', substr($buffer, 252, 1))['v'];
        $data['orderNo'] = unpack('a32v', substr($buffer, 253, 32))['v'];
        $data['cmd'] = 202;
        return $data;
    }

    /**
     * 组装报文
     * @param int $cmd
     * @param string $data
     * @return string
     */
    private static function composeMsg($cmd = 0, $data = '')
    {
        $msg = pack('v', 62890);
        $msg .= pack('v', strlen($data) + 9);
        $msg .= pack('C', 0);
        $msg .= pack('C', 0);
        $msg .= pack('v', $cmd);
        $msg .= $data;
        $msg .= pack('C', self::checkPlus(substr($msg, 6, strlen($data) + 2)));
        return $msg;
    }

    /**
     * 计算校验和
     * @param string $buffer
     * @return int
     */
    private static function checkPlus($buffer = '')
    {
        $bufferArr = str_split($buffer);
        $plus = 0;
        foreach ($bufferArr as $v) {
            $plus += (int)base_convert(bin2hex($v), 16, 10);
        }
        return $plus & 0xFF;
    }

    /**
     * 组装32位字符串(补0)
     * @param string $str
     * @return string
     */
    public static function composeStr32By0($str = '')
    {
        return pack('a32', str_pad($str, 31, 0, STR_PAD_RIGHT));
    }

    /**
     * 解析二进制信息
     * @param string $buffer
     * @return string
     */
    private static function parseBin($buffer = '')
    {
        $bufferArr = str_split($buffer);
        $status = '';
        foreach ($bufferArr as $v) {
            $status .= str_pad(base_convert(bin2hex($v), 16, 2), 8, 0, STR_PAD_LEFT);
        }
        return $status;
    }

    /**
     * 获取时间
     * @param int $timeStamp
     * @return string
     */
    private static function getTime($timeStamp = 0)
    {
        $timeStamp = $timeStamp ?: time();
        $timeArr = str_split(date('YmdHis', $timeStamp + 8 * 3600), 2);
        $timeStr = '';
        foreach ($timeArr as $v) {
            $timeStr .= pack('C', (int)$v);
        }
        return $timeStr . pack('C', 255);
    }

    /**
     * 解析时间
     * @param string $buffer
     * @return mixed
     */
    private static function parseTime($buffer = '')
    {
        $time = str_pad(unpack('Cv', substr($buffer, 0, 1))['v'], 2, 0, STR_PAD_LEFT);
        $time .= str_pad(unpack('Cv', substr($buffer, 1, 1))['v'], 2, 0, STR_PAD_LEFT) . '-';
        $time .= str_pad(unpack('Cv', substr($buffer, 2, 1))['v'], 2, 0, STR_PAD_LEFT) . '-';
        $time .= str_pad(unpack('Cv', substr($buffer, 3, 1))['v'], 2, 0, STR_PAD_LEFT) . ' ';
        $time .= str_pad(unpack('Cv', substr($buffer, 4, 1))['v'], 2, 0, STR_PAD_LEFT) . ':';
        $time .= str_pad(unpack('Cv', substr($buffer, 5, 1))['v'], 2, 0, STR_PAD_LEFT) . ':';
        $time .= str_pad(unpack('Cv', substr($buffer, 6, 1))['v'], 2, 0, STR_PAD_LEFT);
        return $time;
    }

    /**
     * 解析各时段用电信息
     * @param string $buffer
     * @return array
     */
    public static function parseElectricInfo($buffer = '')
    {
        $bufferArr = str_split($buffer, 2);
        foreach ($bufferArr as &$v) {
            $v = unpack('vv', $v)['v'];
        }
        return $bufferArr;
    }
}