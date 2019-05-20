<?php
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
                case 9000:
                    \vendor\handle\EnPile::onMessage($client_id, $message);
                    break;
                case 9001:
                    \vendor\handle\EnAdmin::onMessage($client_id, $message);
                    break;
                case 9002:
                    \vendor\handle\EnUser::onMessage($client_id, $message);
                    break;
            }
        }
    }


    public static function onClose($client_id)
    {
        switch ($_SERVER['GATEWAY_PORT']) {
            case 9000:
                \vendor\handle\EnPile::onClose($client_id);
                break;
            case 9001:
                \vendor\handle\EnAdmin::onClose($client_id);
                break;
            case 9002:
                \vendor\handle\EnUser::onClose($client_id);
                break;
        }
    }
}