<?php declare(strict_types=1);

namespace Plugin\Ranking\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Plugin\Ranking\Enum\Archetype;

final class ArchetypeTest extends TestCase
{
    public function testIsNumericForEloAndPoints(): void
    {
        static::assertTrue(Archetype::Elo->isNumeric());
        static::assertTrue(Archetype::Points->isNumeric());
        static::assertFalse(Archetype::KyuDan->isNumeric());
        static::assertFalse(Archetype::Belt->isNumeric());
        static::assertFalse(Archetype::Division->isNumeric());
    }

    public function testHasTierListInverse(): void
    {
        foreach (Archetype::cases() as $case) {
            static::assertSame(!$case->isNumeric(), $case->hasTierList());
        }
    }

    public function testLabelReturnsTranslationKey(): void
    {
        static::assertSame('ranking.archetype_elo', Archetype::Elo->label());
        static::assertSame('ranking.archetype_belt', Archetype::Belt->label());
    }
}
