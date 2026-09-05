#!/usr/bin/env php
<?php declare(strict_types=1);

$root = dirname(__DIR__);
$env = $argv[1] ?? null;

$configFile = $root . '/config/plugins.php';
if ($env !== null && file_exists($root . '/config/plugins_' . $env . '.php')) {
    $configFile = $root . '/config/plugins_' . $env . '.php';
}

if (!file_exists($configFile)) {
    echo 'Plugin config file not found: ' . $configFile . PHP_EOL;
    exit(1);
}

$config = require $configFile;
if (!is_array($config)) {
    echo 'Plugin config file must return an array: ' . $configFile . PHP_EOL;
    exit(1);
}

/** @var array<string, list<string>> $installed */
$installed = [];
foreach (glob($root . '/plugins/*/manifest.json') ?: [] as $manifestFile) {
    $key = basename(dirname($manifestFile));
    $manifest = json_decode((string) file_get_contents($manifestFile), true);
    $requires = is_array($manifest) && is_array($manifest['requires'] ?? null) ? $manifest['requires'] : [];
    $installed[$key] = array_values(array_map('strval', $requires));
}

$unlisted = [];
$unsatisfied = [];
foreach ($installed as $key => $requires) {
    if (!array_key_exists($key, $config)) {
        $unlisted[] = $key;
        continue;
    }
    foreach ($requires as $required) {
        if (!array_key_exists($required, $config)) {
            $unsatisfied[] = [$key, $required];
        }
    }
}

if ($unlisted === [] && $unsatisfied === []) {
    echo sprintf('Plugin config check passed - %d installed plugin(s) listed in %s.', count($installed), basename($configFile)) . PHP_EOL;
    exit(0);
}

foreach ($unlisted as $key) {
    echo sprintf('%s is installed but has no key in %s', $key, basename($configFile)) . PHP_EOL;
}
foreach ($unsatisfied as [$key, $required]) {
    echo sprintf('%s requires %s, which has no key in %s', $key, $required, basename($configFile)) . PHP_EOL;
}

echo PHP_EOL . 'Add the missing line(s):' . PHP_EOL;
foreach (array_unique([...$unlisted, ...array_column($unsatisfied, 1)]) as $key) {
    echo sprintf("    '%s' => true,", $key) . PHP_EOL;
}
echo PHP_EOL;
echo 'A plugin listed as false stays registered in the container and only loses its routes;' . PHP_EOL;
echo 'a plugin with no key at all registers no services, so anything injecting one fails to compile.' . PHP_EOL;
echo 'See architecture/plugin-system.md.' . PHP_EOL;
exit(1);
