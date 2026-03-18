<?php

declare(strict_types=1);

$loader = require_once __DIR__ . '/../vendor/autoload.php';

if ($loader === true) {
    foreach (spl_autoload_functions() as $fn) {
        if (is_array($fn) && $fn[0] instanceof \Composer\Autoload\ClassLoader) {
            $loader = $fn[0];
            break;
        }
    }
}

foreach (glob(__DIR__ . '/*/src', GLOB_ONLYDIR) as $dir) {
    $pluginName = basename(dirname($dir));
    $namespace = 'Plugin\\' . ucfirst($pluginName) . '\\';
    $loader->addPsr4($namespace, $dir . '/');
}
