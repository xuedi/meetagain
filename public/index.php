<?php

// Check if installation is complete
$lockFile = __DIR__ . '/install/var/installed.lock';
if (!file_exists($lockFile)) {
    // Redirect to installer if not installed
    header('Location: /install/');
    exit;
}

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
