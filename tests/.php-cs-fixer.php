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
        //'@Symfony' => true,
        'PhpCsFixerCustomFixers/declare_after_opening_tag' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/../var/cache/.php-cs-fixer.cache')
    ->setParallelConfig(new PhpCsFixer\Runner\Parallel\ParallelConfig());
