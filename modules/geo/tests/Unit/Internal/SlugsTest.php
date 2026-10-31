<?php declare(strict_types=1);

namespace Module\Geo\Tests\Unit\Internal;

use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Repository\CityRepository;
use Module\Geo\Internal\Slugs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SlugsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNames(): iterable
    {
        yield 'plain' => ['Berlin', 'berlin'];
        yield 'several words' => ['Frankfurt am Main', 'frankfurt-am-main'];
        yield 'umlaut' => ['München', 'munchen'];
        yield 'accent' => ['São Paulo', 'sao-paulo'];
    }

    #[DataProvider('provideNames')]
    public function testANameSlugsToLowercaseAscii(string $name, string $expected): void
    {
        // Arrange
        $slugs = new Slugs(new AsciiSlugger(), self::createStub(CityRepository::class));

        // Act
        $slug = $slugs->of($name);

        // Assert
        self::assertSame($expected, $slug);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function provideTakenSlugs(): iterable
    {
        yield 'nothing taken' => [[], 'springfield'];
        yield 'the plain slug taken' => [['springfield'], 'springfield-2'];
        yield 'the first suffixes taken' => [['springfield', 'springfield-2', 'springfield-3'], 'springfield-4'];
        yield 'a gap is filled' => [['springfield', 'springfield-3'], 'springfield-2'];
        yield 'only a suffix taken' => [['springfield-2'], 'springfield'];
    }

    /**
     * @param list<string> $taken
     */
    #[DataProvider('provideTakenSlugs')]
    public function testAFreeCitySlugAvoidsTheTakenOnes(array $taken, string $expected): void
    {
        // Arrange
        $cities = self::createStub(CityRepository::class);
        $cities->method('findSlugsStartingWith')->willReturn($taken);
        $slugs = new Slugs(new AsciiSlugger(), $cities);

        // Act
        $slug = $slugs->freeCitySlug(new Country(6252001, 'US', 'USA', 'United States', 'united-states'), 'springfield');

        // Assert
        self::assertSame($expected, $slug);
    }
}
