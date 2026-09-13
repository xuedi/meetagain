<?php declare(strict_types=1);

namespace Plugin\Films\Publisher\PluginSettings;

use App\Publisher\PluginSettings\StoreInterface;
use Plugin\Films\Entity\Settings;
use Plugin\Films\Service\SettingsService;

final readonly class SettingsStore implements StoreInterface
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    public function supports(string $key, ?string $scopeId): bool
    {
        return $key === 'films' && $scopeId === null;
    }

    public function load(string $key, ?string $scopeId): ?object
    {
        return $this->settingsService->getOrCreateGlobal();
    }

    public function save(string $key, object $data, ?string $scopeId): void
    {
        \assert($data instanceof Settings);

        $global = $this->settingsService->getOrCreateGlobal();
        $global->setAdapter($data->getAdapter());
        $global->setEncryptedTmdbKey($data->getEncryptedTmdbKey());
        $global->setEncryptedOmdbKey($data->getEncryptedOmdbKey());

        $this->settingsService->save($global);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
