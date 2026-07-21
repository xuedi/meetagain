<?php declare(strict_types=1);

namespace Plugin\Glossary\Migration;

/**
 * Converts an old-shape glossary config (single-label `categories`) to the new per-locale
 * `taxonomy` shape. Shared by the glossary global-config hotfix and any per-scope config hotfix a
 * host plugin ships for its own settings store.
 */
final class LegacyGlossaryCategoryConverter
{
    /**
     * Returns the rewritten config array, or null when there is nothing to convert - a `taxonomy`
     * key is already present, or the config never carried categories - so callers stay idempotent.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>|null
     */
    public static function convert(array $config, string $sourceLocale): ?array
    {
        if (array_key_exists('taxonomy', $config) || !array_key_exists('categories', $config)) {
            return null;
        }

        $categories = [];
        foreach ((array) $config['categories'] as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $categories[] = [
                'id' => (int) ($row['id'] ?? 0),
                'labels' => $label === '' ? [] : [$sourceLocale => $label],
            ];
        }

        $converted = $config;
        unset($converted['categories']);
        $converted['taxonomy'] = [
            'categoriesEnabled' => $categories !== [],
            'tagsEnabled' => false,
            'categories' => $categories,
            'tags' => [],
        ];

        return $converted;
    }
}
