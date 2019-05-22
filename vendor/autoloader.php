<?php

class autoloader
{
    public static function loadFile($name)
    {
        $path = __DIR__ . '/../' . $name . '.php';
        $path = str_replace('\\', '/', $path);
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

spl_autoload_register('autoloader::loadFile');