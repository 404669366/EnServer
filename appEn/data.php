<?php
/**
 * Created by PhpStorm.
 * User: miku
 * Date: 2019/5/21
 * Time: 12:41
 */
require_once '../vendor/globalData/client.php';
$global = new \vendor\globalData\client();
var_dump($global->hGet('pileInfo','pile_20000'));