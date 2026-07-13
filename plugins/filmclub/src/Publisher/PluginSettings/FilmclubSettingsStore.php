<?php declare(strict_types=1);

namespace Plugin\Filmclub\Publisher\PluginSettings;

use App\Publisher\PluginSettings\PluginSettingsStoreInterface;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Plugin\Filmclub\Service\FilmclubSettingsService;

/**
 * Custom store for the filmclub settings: keeps the SecretBox-encrypted entity and its
 * own table. Global scope only; outranks the generic store for the filmclub key.
 */
final readonly class FilmclubSettingsStore implements PluginSettingsStoreInterface
{
    public function __construct(
        private FilmclubSettingsService $settingsService,
    ) {}

    public function supports(string $key, ?string $scopeId): bool
    {
        return $key === 'filmclub' && $scopeId === null;
    }

    public function load(string $key, ?string $scopeId): ?object
    {
        return $this->settingsService->getOrCreateGlobal();
    }

    public function save(string $key, object $data, ?string $scopeId): void
    {
        \assert($data instanceof FilmclubSettings);

        $this->settingsService->save($data);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
