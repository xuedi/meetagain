<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\Set\SymfonySetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../assets',
        __DIR__ . '/../config',
        __DIR__ . '/../public',
        __DIR__ . '/../src',
        __DIR__ . '/../tests',
    ])
    ->withPhpSets(php84: true)
    ->withPHPStanConfigs([__DIR__ . '/phpstan.neon'])
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withTypeCoverageLevel(0)
    ->withSymfonyContainerXml(__DIR__ . '/../var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withSets([
        SymfonySetList::SYMFONY_71,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION
    ])->withRules([
        TypedPropertyFromStrictConstructorRector::class
    ]);
