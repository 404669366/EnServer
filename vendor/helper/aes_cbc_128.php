<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/3/28
 * Time: 17:14
 */

namespace vendor\helper;
class aes_cbc_128
{
    private static $key = 'sGBYahS5eHrh8RJT';
    private static $iv = '0WXOpJ1f1gwTTIJR';
    private static $method = 'aes-128-cbc';

    /**
     * 加密
     * @param string $data
     * @return string
     */
    public static function encode($data = '')
    {
        return openssl_encrypt($data, self::$method, self::$key, true, self::$iv);
    }

    /**
     * 解密
     * @param string $data
     * @return string
     */
    public static function decode($data = '')
    {
        return openssl_decrypt($data, self::$method, self::$key, true, self::$iv);
    }
}