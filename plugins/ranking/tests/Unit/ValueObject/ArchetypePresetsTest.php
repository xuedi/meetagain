<?php declare(strict_types=1);

namespace Plugin\Ranking\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Plugin\Ranking\Enum\Archetype;
use Plugin\Ranking\ValueObject\ArchetypePresets;
use Plugin\Ranking\ValueObject\RankPreset;

final class ArchetypePresetsTest extends TestCase
{
    public function testAllPresetsHaveUniqueKeysAndAtLeastOneEntry(): void
    {
        // Arrange
        $presets = ArchetypePresets::all();
        $seen = [];

        // Act + Assert
        static::assertGreaterThanOrEqual(13, count($presets));
        foreach ($presets as $key => $preset) {
            static::assertInstanceOf(RankPreset::class, $preset);
            static::assertSame($key, $preset->key);
            static::assertArrayNotHasKey($key, $seen);
            $seen[$key] = true;
            static::assertGreaterThan(0, count($preset->entries));
        }
    }

    public function testBeltPresetsHaveValidHexColors(): void
    {
        foreach (ArchetypePresets::forArchetype(Archetype::Belt) as $preset) {
            foreach ($preset->entries as $entry) {
                static::assertNotNull($entry->colorHex, sprintf('Belt entry "%s" missing colorHex', $entry->label));
                static::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $entry->colorHex);
            }
        }
    }

    public function testEloAndPointsHaveNoPresets(): void
    {
        static::assertSame([], ArchetypePresets::forArchetype(Archetype::Elo));
        static::assertSame([], ArchetypePresets::forArchetype(Archetype::Points));
    }

    public function testKyuDanContainsExpectedKeys(): void
    {
        $keys = array_map(static fn(RankPreset $p) => $p->key, ArchetypePresets::forArchetype(Archetype::KyuDan));
        static::assertContains('go-kyu-dan', $keys);
        static::assertContains('cefr', $keys);
        static::assertContains('chess-titles', $keys);
    }

    public function testGetReturnsNullForUnknownKey(): void
    {
        static::assertNull(ArchetypePresets::get('does-not-exist'));
    }

    public function testJudoAdultPresetHasSevenColouredBelts(): void
    {
        // Arrange + Act
        $preset = ArchetypePresets::get('judo-adult');

        // Assert
        static::assertNotNull($preset);
        static::assertSame(Archetype::Belt, $preset->archetype);
        static::assertCount(7, $preset->entries);

        foreach ($preset->entries as $entry) {
            static::assertNotNull($entry->colorHex, sprintf('Judo entry "%s" missing colour', $entry->label));
            static::assertNotNull($entry->labelKey, sprintf('Judo entry "%s" missing labelKey', $entry->label));
            static::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $entry->colorHex);
        }
    }
}
