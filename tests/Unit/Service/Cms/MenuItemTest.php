<?php declare(strict_types=1);

namespace Tests\Unit\Service\Cms;

use App\Entity\Cms;
use App\Service\Cms\MenuItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MenuItemTest extends TestCase
{
    public function testConstructorAssignsAllProperties(): void
    {
        // Arrange / Act
        $item = new MenuItem(slug: '/en/about', name: 'About', priority: 1.5);

        // Assert
        static::assertSame('/en/about', $item->slug);
        static::assertSame('About', $item->name);
        static::assertSame(1.5, $item->priority);
    }

    /**
     * @param array{slug: ?string, linkName: ?string, locale: string} $cmsData
     */
    #[DataProvider('provideFromCmsCases')]
    public function testFromCmsBuildsLocalisedSlugAndFallsBackToSlugForName(array $cmsData, string $expectedSlug, string $expectedName): void
    {
        // Arrange
        $cms = $this->createStub(Cms::class);
        $cms->method('getSlug')->willReturn($cmsData['slug']);
        $cms->method('getLinkName')->willReturn($cmsData['linkName']);

        // Act
        $item = MenuItem::fromCms($cms, $cmsData['locale']);

        // Assert
        static::assertSame($expectedSlug, $item->slug);
        static::assertSame($expectedName, $item->name);
        static::assertSame(0.0, $item->priority);
    }

    public static function provideFromCmsCases(): iterable
    {
        yield 'link name present' => [
            ['slug' => 'about', 'linkName' => 'About Us', 'locale' => 'en'],
            '/en/about',
            'About Us',
        ];
        yield 'link name null falls back to slug' => [
            ['slug' => 'about', 'linkName' => null, 'locale' => 'de'],
            '/de/about',
            'about',
        ];
        yield 'both null falls back to empty string' => [
            ['slug' => null, 'linkName' => null, 'locale' => 'zh'],
            '/zh/',
            '',
        ];
    }
}
