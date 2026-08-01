<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Migration\LegacyTaxonomyConverter;
use App\Repository\PluginSettingsRepository;
use App\Service\AppStateService;
use Override;

readonly class ItemTaxonomyToTags implements DataHotfixInterface
{
    public const string MAP_KEY = 'item_tag_migration.map';
    public const string SNAPSHOT_KEY = 'item_tag_migration.assignments';
    public const string GLOBAL_SCOPE = '';

    private const array TYPE_OF_SETTINGS_KEY = [
        'dishes' => 'dish',
        'books' => 'book',
        'films_taxonomy' => 'film',
        'glossary' => 'glossary',
    ];

    public function __construct(
        private PluginSettingsRepository $settingsRepository,
        private LegacyTaxonomyConverter $converter,
        private AppStateService $appState,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_08_01_item_taxonomy_to_tags';
    }

    #[Override]
    public function execute(): void
    {
        $snapshot = $this->read(self::SNAPSHOT_KEY);
        if ($snapshot === []) {
            $snapshot = $this->converter->snapshotAssignments();
            $this->appState->set(self::SNAPSHOT_KEY, (string) json_encode($snapshot));
        }

        $map = $this->read(self::MAP_KEY);
        foreach (self::TYPE_OF_SETTINGS_KEY as $settingsKey => $itemType) {
            $taxonomy = $this->storedTaxonomy($settingsKey);
            if ($taxonomy === null || $this->converter->isConverted($itemType, self::GLOBAL_SCOPE, $map)) {
                continue;
            }

            $map = [...$map, ...$this->converter->convertVocabulary($itemType, self::GLOBAL_SCOPE, $taxonomy)['map']];
        }
        $this->appState->set(self::MAP_KEY, (string) json_encode($map));

        $categories = $this->converter->legacyCategories();
        foreach (array_keys([...$categories, ...$snapshot]) as $itemKey) {
            [$itemType, $itemId] = explode('|', $itemKey);
            if (!$this->converter->isConverted($itemType, self::GLOBAL_SCOPE, $map)) {
                continue;
            }

            $this->converter->rewriteAssignments(
                $itemType,
                (int) $itemId,
                self::GLOBAL_SCOPE,
                $categories[$itemKey] ?? null,
                $snapshot[$itemKey] ?? [],
                $map,
            );
        }
    }

    /** @return array<string, mixed> */
    private function read(string $key): array
    {
        $raw = $this->appState->get($key);
        $decoded = $raw === null ? null : json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed>|null */
    private function storedTaxonomy(string $settingsKey): ?array
    {
        $data = $this->settingsRepository->findOneByPluginKey($settingsKey)?->getData() ?? [];
        $taxonomy = $data['taxonomy'] ?? null;

        return is_array($taxonomy) ? $taxonomy : null;
    }
}
