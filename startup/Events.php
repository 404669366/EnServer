<?php

require_once '../handle/TldPile.php';
require_once '../handle/Web.php';

class Events
{
    public static function onWorkerStart($businessWorker)
    {

    }

    public static function onConnect($client_id)
    {

    }


    public static function onMessage($client_id, $message)
    {
        switch ($_SERVER['GATEWAY_PORT']) {
            case 20000:
                \handle\Web::onMessage($client_id, $message);
                break;
            case 20001:
                \handle\TldPile::onMessage($client_id, $message);
                break;

        }
    }


    public static function onClose($client_id)
    {
        switch ($_SERVER['GATEWAY_PORT']) {
            case 20000:
                \handle\Web::onClose($client_id);
                break;
            case 20001:
                \handle\TldPile::onClose($client_id);
                break;
        }
    }
}