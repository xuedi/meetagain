<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/../src',
        __DIR__ . '/../tests',
        __DIR__ . '/../public/install',
    ])
    ->exclude('var');

return (new PhpCsFixer\Config())
    ->registerCustomFixers(new PhpCsFixerCustomFixers\Fixers())
    ->setRules([
        '@Symfony' => true,
        'PhpCsFixerCustomFixers/declare_after_opening_tag' => true,
        'global_namespace_import' => ['import_classes' => true],
        'yoda_style' => false,
        'concat_space' => ['spacing' => 'one'],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/../var/cache/.php-cs-fixer.cache')
    ->setParallelConfig(new PhpCsFixer\Runner\Parallel\ParallelConfig());
