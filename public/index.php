<?php

// Check if this instance needs to be installed
$lockFile = dirname(__DIR__) . '/installed.lock';
if (!file_exists($lockFile)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_starts_with($requestUri, '/install')) {
        require __DIR__ . '/install/index.php';
        exit;
    }

    // Otherwise redirect to installer
    header('Location: /install/');
    exit;
}

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
