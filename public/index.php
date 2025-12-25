<?php

// Check if the installation is complete
$lockFile = dirname(__DIR__) . '/installed.lock';
if (!file_exists($lockFile)) {
    header('Location: /install/');
    exit;
}

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
