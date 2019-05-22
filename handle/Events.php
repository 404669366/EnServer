<?php

namespace handle;
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
        if ($message) {
            switch ($_SERVER['GATEWAY_PORT']) {
                case 20000:
                    EnPile::onMessage($client_id, $message);
                    break;
                case 20001:
                    EnUse::onMessage($client_id, $message);
                    break;
            }
        }
    }


    public static function onClose($client_id)
    {
        switch ($_SERVER['GATEWAY_PORT']) {
            case 20000:
                EnPile::onClose($client_id);
                break;
            case 20001:
                EnUse::onClose($client_id);
                break;
        }
    }
}