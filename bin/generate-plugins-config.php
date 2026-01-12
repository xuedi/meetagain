#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Generates config/plugins.php based on the specified mode.
 *
 * Usage:
 *   php bin/generate-plugins-config.php [mode]
 *
 * Modes:
 *   no     - All plugins disabled (default)
 *   all    - All plugins enabled
 *   <name> - Enable specific plugin(s), comma-separated (e.g., "dishes" or "dishes,glossary")
 */

$projectRoot = dirname(__DIR__);
$pluginsDir = $projectRoot . '/plugins';
$configFile = $projectRoot . '/config/plugins.php';

// Get mode from argument (default: 'no')
$mode = $argv[1] ?? 'no';

// Discover available plugins
$availablePlugins = [];
if (is_dir($pluginsDir)) {
    foreach (scandir($pluginsDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $pluginPath = $pluginsDir . '/' . $entry;
        if (is_dir($pluginPath) && file_exists($pluginPath . '/manifest.json')) {
            $availablePlugins[] = $entry;
        }
    }
}

sort($availablePlugins);

// Build plugin configuration based on mode
$enabledPlugins = [];

switch ($mode) {
    case 'no':
    case 'none':
    case '':
        // All plugins disabled
        foreach ($availablePlugins as $plugin) {
            $enabledPlugins[$plugin] = false;
        }
        break;

    case 'all':
        // All plugins enabled
        foreach ($availablePlugins as $plugin) {
            $enabledPlugins[$plugin] = true;
        }
        break;

    default:
        // Specific plugin(s) - comma-separated
        $requestedPlugins = array_map('trim', explode(',', $mode));

        // Validate requested plugins exist
        foreach ($requestedPlugins as $requested) {
            if (!in_array($requested, $availablePlugins, true)) {
                fwrite(STDERR, "Error: Plugin '$requested' not found.\n");
                fwrite(STDERR, "Available plugins: " . implode(', ', $availablePlugins) . "\n");
                exit(1);
            }
        }

        // Enable only requested plugins
        foreach ($availablePlugins as $plugin) {
            $enabledPlugins[$plugin] = in_array($plugin, $requestedPlugins, true);
        }
        break;
}

// Generate PHP config file
$configContent = "<?php declare(strict_types=1); return [\n";
foreach ($enabledPlugins as $plugin => $enabled) {
    $value = $enabled ? 'true' : 'false';
    $configContent .= "    '$plugin' => $value,\n";
}
$configContent .= "];\n";

// Write config file
file_put_contents($configFile, $configContent);

// Output summary
$enabledCount = count(array_filter($enabledPlugins));
$totalCount = count($enabledPlugins);

echo "Generated config/plugins.php\n";
echo "Plugins: $enabledCount/$totalCount enabled\n";

if ($enabledCount > 0) {
    $enabledNames = array_keys(array_filter($enabledPlugins));
    echo "Enabled: " . implode(', ', $enabledNames) . "\n";
}
