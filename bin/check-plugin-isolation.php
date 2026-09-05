#!/usr/bin/env php
<?php declare(strict_types=1);

$root = dirname(__DIR__);
$generatedConfig = $root . '/config/plugins_prod.php';
$cacheDir = $root . '/var/cache/prod';

if (file_exists($root . '/.env.local.php')) {
    echo 'Refusing to run on a deployed install: this check deletes var/cache/prod.' . PHP_EOL;
    exit(1);
}
if (file_exists($generatedConfig)) {
    echo 'Refusing to overwrite an existing config/plugins_prod.php - remove it first.' . PHP_EOL;
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

if ($installed === []) {
    echo 'No plugins installed - nothing to check.' . PHP_EOL;
    exit(0);
}

$cleanup = static function () use ($generatedConfig): void {
    if (file_exists($generatedConfig)) {
        unlink($generatedConfig);
    }
};
register_shutdown_function($cleanup);
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, static function () use ($cleanup): void {
            $cleanup();
            exit(130);
        });
    }
}

function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($dir);
}

/**
 * @param array<string, list<string>> $installed
 * @return list<string>
 */
function setWithout(array $installed, string $dropped): array
{
    $kept = array_diff(array_keys($installed), [$dropped]);
    do {
        $before = $kept;
        foreach ($kept as $key) {
            foreach ($installed[$key] as $required) {
                if (!in_array($required, $kept, true)) {
                    $kept = array_diff($kept, [$key]);
                    break;
                }
            }
        }
    } while ($kept !== $before);

    return array_values($kept);
}

$failures = [];
foreach (array_keys($installed) as $dropped) {
    $kept = setWithout($installed, $dropped);
    $lines = ['<?php declare(strict_types=1);', '', 'return ['];
    foreach ($kept as $key) {
        $lines[] = sprintf("    '%s' => true,", $key);
    }
    $lines[] = '];';
    file_put_contents($generatedConfig, implode(PHP_EOL, $lines) . PHP_EOL);

    removeDirectory($cacheDir);
    $command = sprintf(
        'COLUMNS=240 %s %s lint:container --env=prod --no-ansi 2>&1',
        escapeshellarg(PHP_BINARY),
        escapeshellarg($root . '/bin/console'),
    );
    exec($command, $output, $exitCode);

    $alsoDropped = array_diff(array_keys($installed), $kept, [$dropped]);
    $label = $dropped . ($alsoDropped === [] ? '' : ' (+ dependents: ' . implode(', ', $alsoDropped) . ')');
    if ($exitCode === 0) {
        echo sprintf('  without %-40s ok', $label) . PHP_EOL;
    } else {
        $reason = '';
        foreach ($output as $line) {
            $line = trim($line);
            if (str_contains($line, 'Cannot autowire') || str_contains($line, 'references class')) {
                $reason .= ($reason === '' ? '' : ' ') . $line;
            }
        }
        $failures[$dropped] = $reason === '' ? implode(' ', array_slice($output, 0, 5)) : $reason;
        echo sprintf('  without %-40s FAILED', $label) . PHP_EOL;
    }
    $output = [];
}

$cleanup();
removeDirectory($cacheDir);

if ($failures === []) {
    echo sprintf('Plugin isolation check passed - %d leave-one-out combination(s) compile.', count($installed)) . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
foreach ($failures as $dropped => $reason) {
    echo sprintf('Removing %s breaks the container: %s', $dropped, $reason) . PHP_EOL;
}
echo PHP_EOL;
echo 'Code that is registered in the production container must not inject another plugin\'s service.' . PHP_EOL;
echo 'Declare the dependency in the plugin manifest ("requires") when the feature genuinely needs it.' . PHP_EOL;
echo 'See architecture/plugin-system.md.' . PHP_EOL;
exit(1);
