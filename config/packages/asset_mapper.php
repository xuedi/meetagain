<?php declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $projectDir = realpath(__DIR__ . '/../..');

    $paths = [
        // Core assets (no namespace — files at assets/styles/app.css are referenced as 'styles/app.css')
        $projectDir . '/assets/' => '',
    ];

    // Plugin assets — auto-discovered, no plugin names in core.
    // A plugin gains asset serving simply by creating a plugins/{key}/assets/ directory.
    $pluginGlob = glob($projectDir . '/plugins/*/assets');
    if ($pluginGlob !== false) {
        foreach ($pluginGlob as $pluginAssetPath) {
            if (is_dir($pluginAssetPath)) {
                $pluginKey = basename(dirname($pluginAssetPath));
                // Namespace prefix: plugins/filmclub/assets/styles/film.css → logical path 'plugins/filmclub/styles/film.css'
                $paths[$pluginAssetPath . '/'] = 'plugins/' . $pluginKey;
            }
        }
    }

    $container->extension('framework', [
        'asset_mapper' => [
            'paths' => $paths,
            'missing_import_mode' => 'strict',
            'excluded_patterns' => [
                '**/_*.scss',
                // Bulma vendor tree: only the sass/ source is needed (used at compile time by sass:build).
                // Everything else — pre-built CSS variants, docs, package files — must not be served.
                '**/styles/vendor/bulma/**',
            ],
            'importmap_path' => '%kernel.project_dir%/config/importmap.php',
        ],
    ]);
};
