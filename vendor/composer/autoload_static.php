<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit867b1d3b5b22c1eb52d09813dc397fbe
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Workerman\\' => 10,
        ),
        'P' => 
        array (
            'PHPSocketIO\\' => 12,
        ),
        'G' => 
        array (
            'GlobalData\\' => 11,
            'GatewayWorker\\' => 14,
        ),
        'C' => 
        array (
            'Channel\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Workerman\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/workerman',
        ),
        'PHPSocketIO\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/phpsocket.io/src',
        ),
        'GlobalData\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/globaldata/src',
        ),
        'GatewayWorker\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/gateway-worker/src',
        ),
        'Channel\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/channel/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit867b1d3b5b22c1eb52d09813dc397fbe::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit867b1d3b5b22c1eb52d09813dc397fbe::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
