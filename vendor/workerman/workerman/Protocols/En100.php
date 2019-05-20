<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/2/28
 * Time: 12:38
 */

namespace Workerman\Protocols;

class En100
{
    private static $head = 22;
    private static $headStructure = 'A16no/Scommand/Ilength';// 16 + 2 + 4
    private static $headInfo;

    public static function input($buffer)
    {
        if (strlen($buffer) < self::$head) {
            return 0;
        }
        $head = @unpack(self::$headStructure, $buffer);
        var_dump($head);
        if ($head) {
            self::$headInfo = $head;
            return self::$head + $head['length'];
        }
        return false;
    }

    public static function decode($buffer)
    {
        $head = self::$headInfo;
        return [
            'no' => $head['no'],
            'command' => $head['command'],
            'body' => substr($buffer, self::$head)
        ];
    }

    public static function encode($data)
    {
        $str = pack('A16', '');
        $str .= pack('S', $data['command']);
        $str .= pack('I', strlen($data['body']));
        $str .= $data['body'];
        return $str;
    }
}