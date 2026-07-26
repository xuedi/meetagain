<?php declare(strict_types=1);

namespace Plugin\Glossary\Migration;

final class LegacyGlossaryCategoryConverter
{
    /**
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
