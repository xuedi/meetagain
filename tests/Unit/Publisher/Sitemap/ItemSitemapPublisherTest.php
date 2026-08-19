<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\Sitemap;

use App\Item\ListProviderInterface;
use App\Item\ListRegistry;
use App\Item\Tag\FacetService;
use App\Item\Tag\TagService;
use App\Repository\ItemTagAssignmentRepository;
use App\Publisher\Sitemap\ItemSitemapPublisher;
use App\Publisher\UrlOwner\UrlOwnerProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Seo\UrlOwnerService;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ItemSitemapPublisherTest extends TestCase
{
    public function testEmitsTheListPageOncePerLocaleWithFullAlternates(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en', 'de', 'zh'],
            providers: ['dish' => $this->makeProvider('app_dish_list', null, [])],
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertCount(3, $urls);
        foreach ($urls as $url) {
            self::assertSame('items', $url->section);
            self::assertSame(0.7, $url->priority);
            self::assertSame('weekly', $url->changefreq);
            self::assertSame(['en', 'de', 'zh'], array_keys($url->alternates));
        }
    }

    public function testATypeWithoutADetailRouteEmitsOnlyItsListPage(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            providers: ['dish' => $this->makeProvider('app_dish_list', null, [3, 4])],
        );

        // Act
        $locs = array_map(static fn($url) => $url->loc, $publisher->getSitemapUrls());

        // Assert
        self::assertSame(['https://example.com/en/app_dish_list'], $locs);
    }

    public function testEmitsOneDetailUrlPerItemAndLocale(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            providers: ['dish' => $this->makeProvider('app_dish_list', 'app_dish_show', [3, 4])],
        );

        // Act
        $detailUrls = array_values(array_filter($publisher->getSitemapUrls(), static fn($url) => isset($url->meta['item_id'])));
        $locs = array_map(static fn($url) => $url->loc, $detailUrls);

        // Assert
        self::assertSame([
            'https://example.com/en/app_dish_show/3',
            'https://example.com/de/app_dish_show/3',
            'https://example.com/en/app_dish_show/4',
            'https://example.com/de/app_dish_show/4',
        ], $locs);
        foreach ($detailUrls as $url) {
            self::assertSame(0.5, $url->priority);
            self::assertSame('monthly', $url->changefreq);
            self::assertSame('dish', $url->meta['item_type']);
        }
    }

    public function testEmitsTheCreationDateAsLastmodWhereTheProviderKnowsOne(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            providers: ['dish' => $this->makeProvider('app_dish_list', 'app_dish_show', [3, 4], [3 => new DateTimeImmutable('2026-02-11')])],
        );

        // Act
        $byId = [];
        foreach ($publisher->getSitemapUrls() as $url) {
            if (!isset($url->meta['item_id'])) {
                continue;
            }

            $byId[$url->meta['item_id']] = $url->lastmod;
        }

        // Assert
        self::assertSame('2026-02-11', $byId[3]?->format('Y-m-d'));
        self::assertNull($byId[4]);
    }

    public function testEmitsNothingForAnItemTypeWhoseIdsAreAllFilteredAway(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            providers: ['dish' => $this->makeProvider('app_dish_list', 'app_dish_show', [])],
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertCount(1, $urls);
        self::assertSame('https://example.com/en/app_dish_list', $urls[0]->loc);
    }

    public function testAnActiveFacetDoesNotShrinkTheFeed(): void
    {
        // Arrange
        $facetService = $this->makeFacetService($this->requestStackWith(new Request(['tag' => ['9']])));
        $publisher = $this->makePublisher(
            locales: ['en'],
            providers: ['dish' => $this->facetSensitiveProvider($facetService)],
            facetService: $facetService,
        );

        // Act
        $locs = array_map(static fn($url) => $url->loc, $publisher->getSitemapUrls());

        // Assert
        self::assertContains('https://example.com/en/app_dish_show/3', $locs);
        self::assertContains('https://example.com/en/app_dish_show/4', $locs);
    }

    public function testCoversEveryActiveItemType(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            providers: [
                'dish' => $this->makeProvider('app_dish_list', 'app_dish_show', [1]),
                'film' => $this->makeProvider('app_film_list', 'app_film_show', [2]),
            ],
        );

        // Act
        $types = array_values(array_unique(array_map(static fn($url) => $url->meta['item_type'], $publisher->getSitemapUrls())));

        // Assert
        self::assertSame(['dish', 'film'], $types);
    }

    public function testEmitsNothingWithoutEnabledLocales(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: [],
            providers: ['dish' => $this->makeProvider('app_dish_list', 'app_dish_show', [3])],
        );

        // Act & Assert
        self::assertSame([], $publisher->getSitemapUrls());
    }

    public function testDropsAnItemTypeAnotherHostOwns(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            providers: ['dish' => $this->makeProvider('app_dish_list', 'app_dish_show', [3, 4])],
            foreignOwnedRoutes: ['app_dish_list', 'app_dish_show'],
        );

        // Act & Assert
        self::assertSame([], $publisher->getSitemapUrls());
    }

    /**
     * @param array<string> $locales
     * @param array<string, ListProviderInterface> $providers
     * @param array<string> $foreignOwnedRoutes routes another host owns, so this feed must drop them
     */
    private function makePublisher(
        array $locales,
        array $providers,
        ?FacetService $facetService = null,
        array $foreignOwnedRoutes = [],
    ): ItemSitemapPublisher {
        $registry = $this->createStub(ListRegistry::class);
        $registry->method('activeProviders')->willReturn($providers);

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredEnabledCodes')->willReturn($locales);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params = []): string {
                $locale = $params['_locale'] ?? 'en';
                $id = $params['id'] ?? null;

                return "https://example.com/{$locale}/{$route}" . ($id !== null ? "/{$id}" : '');
            },
        );

        $config = $this->createStub(ConfigService::class);
        $config->method('getHost')->willReturn('https://example.com');

        $ownerProvider = $this->createStub(UrlOwnerProviderInterface::class);
        $ownerProvider
            ->method('getOwnerHost')
            ->willReturnCallback(
                static fn(string $route) => in_array($route, $foreignOwnedRoutes, true) ? 'https://other.example.com' : null,
            );

        return new ItemSitemapPublisher(
            $registry,
            $facetService ?? $this->makeFacetService($this->requestStackWith(null)),
            $language,
            $urlGenerator,
            new UrlOwnerService($config, [$ownerProvider]),
        );
    }

    public function testATypeThatOptsOutEmitsItsListPageButNoDetailPages(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en', 'de', 'zh'],
            providers: [
                'glossary' => $this->makeProvider('app_glossary_list', 'app_glossary_show', [7, 8], [], false),
            ],
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertCount(3, $urls, 'Only the list page, once per locale');
        foreach ($urls as $url) {
            self::assertStringNotContainsString('/glossary/', $url->loc);
        }
    }

    /**
     * @param list<int> $itemIds
     * @param array<int, DateTimeInterface> $lastmods
     */
    private function makeProvider(
        string $listRoute,
        ?string $detailRoute,
        array $itemIds,
        array $lastmods = [],
        bool $detailIndexable = true,
    ): ListProviderInterface {
        $provider = $this->createStub(ListProviderInterface::class);
        $provider->method('getListRoute')->willReturn($listRoute);
        $provider->method('getDetailRoute')->willReturn($detailRoute);
        $provider->method('isDetailIndexable')->willReturn($detailIndexable);
        $provider->method('getItemIds')->willReturn($itemIds);
        $provider->method('getLastmodByItemId')->willReturn($lastmods);

        return $provider;
    }

    private function facetSensitiveProvider(FacetService $facetService): ListProviderInterface
    {
        $provider = $this->createStub(ListProviderInterface::class);
        $provider->method('getListRoute')->willReturn('app_dish_list');
        $provider->method('getDetailRoute')->willReturn('app_dish_show');
        $provider->method('isDetailIndexable')->willReturn(true);
        $provider->method('getItemIds')->willReturnCallback(
            static fn(): array => $facetService->current()->tags === [] ? [3, 4] : [3],
        );
        $provider->method('getLastmodByItemId')->willReturn([]);

        return $provider;
    }

    private function makeFacetService(RequestStack $stack): FacetService
    {
        return new FacetService(
            $stack,
            $this->createStub(ListRegistry::class),
            $this->createStub(TagService::class),
            $this->createStub(ItemTagAssignmentRepository::class),
        );
    }

    private function requestStackWith(?Request $request): RequestStack
    {
        $stack = new RequestStack();
        if ($request !== null) {
            $stack->push($request);
        }

        return $stack;
    }
}
