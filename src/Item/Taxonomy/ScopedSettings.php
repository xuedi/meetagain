<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use App\Publisher\PluginSettings\Resolver;
use App\Service\Admin\PluginSettingsService;

final readonly class ScopedSettings
{
    public function __construct(
        private Resolver $resolver,
        private PluginSettingsService $descriptors,
    ) {}

    public function load(string $settingsKey, ?string $scopeId): ?object
    {
        if (!$this->isWritable($settingsKey, $scopeId)) {
            return null;
        }

        $stored = $this->resolver->resolveStore($settingsKey, $scopeId)?->load($settingsKey, $scopeId);
        if ($stored !== null) {
            return $stored;
        }

        return $scopeId !== null
            ? $this->resolver->resolve($settingsKey)
            : $this->descriptors->getProvider($settingsKey)?->createDefault();
    }

    public function save(string $settingsKey, object $data, ?string $scopeId): void
    {
        $this->resolver->resolveStore($settingsKey, $scopeId)?->save($settingsKey, $data, $scopeId);
    }

    public function isWritable(string $settingsKey, ?string $scopeId): bool
    {
        $descriptor = $this->descriptors->getProvider($settingsKey);
        if ($descriptor === null || ($scopeId !== null && !$descriptor->isScopable())) {
            return false;
        }

        return $this->resolver->resolveStore($settingsKey, $scopeId) !== null;
    }
}
