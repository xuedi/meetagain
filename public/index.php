<?php declare(strict_types=1);

$lockFile = dirname(__DIR__) . '/installed.lock';
if (!file_exists($lockFile)) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_starts_with($requestUri, '/install')) {
        require __DIR__ . '/install/index.php';
        exit();
    }

    header('Location: /install/');
    exit();
}

use App\Kernel;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return fn(array $context) => new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
