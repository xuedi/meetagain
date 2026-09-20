#!/usr/bin/env php
<?php declare(strict_types=1);

$env = $argv[1] ?? null;
$root = $argv[2] ?? dirname(__DIR__);

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

$stale = [];
foreach (array_keys($config) as $key) {
    if (!is_dir($root . '/plugins/' . $key . '/config')) {
        $stale[] = (string) $key;
    }
}

if ($unlisted === [] && $unsatisfied === [] && $stale === []) {
    echo sprintf('Plugin config check passed - %d installed plugin(s) listed in %s.', count($installed), basename($configFile)), PHP_EOL;
    exit(0);
}

foreach ($unlisted as $key) {
    echo sprintf('%s is installed but has no key in %s', $key, basename($configFile)), PHP_EOL;
}
foreach ($unsatisfied as [$key, $required]) {
    echo sprintf('%s requires %s, which has no key in %s', $key, $required, basename($configFile)), PHP_EOL;
}
foreach ($stale as $key) {
    echo sprintf('%s has a key in %s but no plugins/%s/config directory', $key, basename($configFile), $key), PHP_EOL;
}

$missing = array_unique([...$unlisted, ...array_column($unsatisfied, 1)]);
if ($missing !== []) {
    echo PHP_EOL . 'Add the missing line(s):' . PHP_EOL;
    foreach ($missing as $key) {
        echo sprintf("    '%s' => true,", $key), PHP_EOL;
    }
}
if ($stale !== []) {
    echo PHP_EOL . 'Delete the stale line(s):' . PHP_EOL;
    foreach ($stale as $key) {
        echo sprintf("    '%s' => %s,", $key, var_export($config[$key], true)), PHP_EOL;
    }
}
echo PHP_EOL;
echo 'A plugin listed as false stays registered in the container and only loses its routes;' . PHP_EOL;
echo 'a plugin with no key at all registers no services, so anything injecting one fails to compile;' . PHP_EOL;
echo 'a key with no plugin directory behind it describes nothing and is ignored by the container.' . PHP_EOL;
echo 'See architecture/plugin-system.md.' . PHP_EOL;
exit(1);
