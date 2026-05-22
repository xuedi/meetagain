<?php declare(strict_types=1);

namespace Tests\Unit\Service\Cms;

use App\Entity\Cms;
use App\Enum\MenuLocation;
use App\Filter\Cms\CmsFilterResult;
use App\Filter\Cms\CmsFilterService;
use App\Repository\CmsRepository;
use App\Service\Cms\MenuService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class MenuServiceTest extends TestCase
{
    #[DataProvider('provideUnknownMenuTypeCases')]
    public function testGetMenuForContextReturnsEmptyListForUnknownType(string $unknownType): void
    {
        // Arrange - repo must not be touched for unknown types
        $cmsRepo = $this->createMock(CmsRepository::class);
        $cmsRepo->expects($this->never())->method('findByMenuLocation');

        $service = $this->createService(cmsRepo: $cmsRepo);

        // Act / Assert
        static::assertSame([], $service->getMenuForContext($unknownType, null, 'en'));
    }

    public static function provideUnknownMenuTypeCases(): iterable
    {
        yield 'empty' => [''];
        yield 'unmapped value' => ['sidebar'];
        yield 'mixed case mismatch' => ['Top'];
    }

    /**
     * @param list<int|string> $cmsIds
     * @param list<string>     $expectedSlugs
     */
    #[DataProvider('provideMenuLocationCases')]
    public function testGetMenuForContextResolvesEachKnownTypeToItsLocation(
        string $type,
        MenuLocation $expectedLocation,
        array $cmsIds,
        array $expectedSlugs,
    ): void {
        // Arrange - repo records the location it was asked for and returns matching pages
        $pages = [
            $this->makeCmsStub(1, 'home', 'Home'),
            $this->makeCmsStub(2, 'about', 'About'),
        ];
        $capturedLocation = null;
        $cmsRepo = $this->createStub(CmsRepository::class);
        $cmsRepo
            ->method('findByMenuLocation')
            ->willReturnCallback(static function (MenuLocation $loc) use ($pages, &$capturedLocation): array {
                $capturedLocation = $loc;
                return $pages;
            });

        $filterService = $this->createStub(CmsFilterService::class);
        $filterService
            ->method('getCmsIdFilter')
            ->willReturn($cmsIds === [] ? CmsFilterResult::noFilter() : new CmsFilterResult($cmsIds, true));

        $service = $this->createService(cmsRepo: $cmsRepo, filterService: $filterService);

        // Act
        $items = $service->getMenuForContext($type, null, 'en');

        // Assert - location lookup matches the mapping, and filter narrows the result
        static::assertSame($expectedLocation, $capturedLocation);
        static::assertSame($expectedSlugs, array_map(static fn($i) => $i->slug, $items));
    }

    public static function provideMenuLocationCases(): iterable
    {
        yield 'top resolves to TopBar, no filter returns all pages' => [
            'top',
            MenuLocation::TopBar,
            [],
            ['/en/home', '/en/about'],
        ];
        yield 'col1 resolves to BottomCol1' => [
            'col1',
            MenuLocation::BottomCol1,
            [],
            ['/en/home', '/en/about'],
        ];
        yield 'col2 resolves to BottomCol2' => [
            'col2',
            MenuLocation::BottomCol2,
            [],
            ['/en/home', '/en/about'],
        ];
        yield 'col3 resolves to BottomCol3' => [
            'col3',
            MenuLocation::BottomCol3,
            [],
            ['/en/home', '/en/about'],
        ];
        yield 'col4 resolves to BottomCol4' => [
            'col4',
            MenuLocation::BottomCol4,
            [],
            ['/en/home', '/en/about'],
        ];
        yield 'allowed-cms-ids filter narrows to a single page' => [
            'top',
            MenuLocation::TopBar,
            [2],
            ['/en/about'],
        ];
        yield 'allowed-cms-ids filter excluding all returns empty list' => [
            'top',
            MenuLocation::TopBar,
            [999],
            [],
        ];
    }

    public function testGetMenuForContextCachesPerCacheKey(): void
    {
        // Arrange - repo should only be hit on first call; second call comes from cache
        $cmsRepo = $this->createMock(CmsRepository::class);
        $cmsRepo
            ->expects($this->once())
            ->method('findByMenuLocation')
            ->willReturn([$this->makeCmsStub(1, 'home', 'Home')]);

        $service = $this->createService(cmsRepo: $cmsRepo);

        // Act
        $first = $service->getMenuForContext('top', null, 'en');
        $second = $service->getMenuForContext('top', null, 'en');

        // Assert
        static::assertCount(1, $first);
        static::assertEquals($first, $second);
    }

    private function createService(?CmsRepository $cmsRepo = null, ?CmsFilterService $filterService = null): MenuService
    {
        if ($filterService === null) {
            $filterService = $this->createStub(CmsFilterService::class);
            $filterService->method('getCmsIdFilter')->willReturn(CmsFilterResult::noFilter());
        }
        return new MenuService(
            $cmsRepo ?? $this->createStub(CmsRepository::class),
            $filterService,
            new TagAwareAdapter(new ArrayAdapter()),
        );
    }

    private function makeCmsStub(int $id, string $slug, string $linkName): Cms
    {
        $cms = $this->createStub(Cms::class);
        $cms->method('getId')->willReturn($id);
        $cms->method('getSlug')->willReturn($slug);
        $cms->method('getLinkName')->willReturn($linkName);
        return $cms;
    }
}
