<?php declare(strict_types=1);

namespace Plugin\Films\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsStoreInterface;
use Plugin\Films\Entity\FilmsSettings;
use Plugin\Films\Service\FilmsSettingsService;

/**
 * Custom store for the films settings: keeps the SecretBox-encrypted entity and its
 * own table. Global scope only; outranks the generic store for the films key.
 */
final readonly class FilmsSettingsStore implements PluginSettingsStoreInterface
{
    public function __construct(
        private FilmsSettingsService $settingsService,
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
        \assert($data instanceof FilmsSettings);

        $this->settingsService->save($data);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
