<?php declare(strict_types=1);

namespace Module\Geo\Tests\Unit\Internal\Import;

use Module\Geo\Internal\Import\NamePicks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NamePicksTest extends TestCase
{
    private const int MUNICH = 2867714;

    /**
     * @return iterable<string, array{list<array{string, bool, bool, bool, bool}>, string|null}>
     */
    public static function provideOffers(): iterable
    {
        yield 'a preferred name beats an earlier plain one' => [
            [
                ['Muenchen', false, false, false, false],
                ['München',  true,  false, false, false],
            ],
            'München',
        ];
        yield 'a preferred name beats a later plain one' => [
            [
                ['München',  true,  false, false, false],
                ['Muenchen', false, false, false, false],
            ],
            'München',
        ];
        yield 'a preferred short name beats a preferred long one' => [
            [
                ['Bundesrepublik Deutschland', true, false, false, false],
                ['Deutschland',                true, true,  false, false],
            ],
            'Deutschland',
        ];
        yield 'a plain name beats a short one' => [
            [
                ['MUC',      false, true,  false, false],
                ['Muenchen', false, false, false, false],
            ],
            'Muenchen',
        ];
        yield 'a short name is taken when nothing else exists' => [[['MUC', false, true, false, false]], 'MUC'];
        yield 'the first of two plain names stays' => [
            [
                ['Muenchen', false, false, false, false],
                ['Munchen',  false, false, false, false],
            ],
            'Muenchen',
        ];
        yield 'a historic name is never taken' => [[['Monacum', true, false, false, true]], null];
        yield 'a colloquial name is never taken' => [[['Minga', false, false, true, false]], null];
        yield 'a blank name is never taken' => [[['  ', true, false, false, false]], null];
    }

    /**
     * @param list<array{string, bool, bool, bool, bool}> $offers
     */
    #[DataProvider('provideOffers')]
    public function testTheBestNamePerLanguageWins(array $offers, ?string $expected): void
    {
        // Arrange
        $picks = new NamePicks();

        // Act
        foreach ($offers as [$name, $preferred, $short, $colloquial, $historic]) {
            $picks->offer(self::MUNICH, 'de', $name, $preferred, $short, $colloquial, $historic);
        }

        // Assert
        self::assertSame($expected, $picks->all()[self::MUNICH]['de'] ?? null);
    }

    public function testEachLanguageIsPickedOnItsOwn(): void
    {
        // Arrange
        $picks = new NamePicks();

        // Act
        $picks->offer(self::MUNICH, 'de', 'München', true, false, false, false);
        $picks->offer(self::MUNICH, 'en', 'Munich', false, false, false, false);

        // Assert
        self::assertSame([self::MUNICH => ['de' => 'München', 'en' => 'Munich']], $picks->all());
    }
}
