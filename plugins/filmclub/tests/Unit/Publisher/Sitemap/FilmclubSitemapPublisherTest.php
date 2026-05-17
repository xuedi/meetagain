<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Publisher\Sitemap;

use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Publisher\Sitemap\FilmclubSitemapPublisher;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\SelectionService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FilmclubSitemapPublisherTest extends TestCase
{
    public function testEmitsFilmlistForEachLocale(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en', 'de'], films: []);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertCount(2, $urls);
        foreach ($urls as $url) {
            self::assertSame(0.7, $url->priority);
            self::assertSame('weekly', $url->changefreq);
            self::assertCount(2, $url->alternates);
        }
    }

    public function testEmitsPerFilmEntriesInAdditionToIndex(): void
    {
        // Arrange
        $film = $this->createStub(Film::class);
        $film->method('getId')->willReturn(42);
        $film->method('getTitle')->willReturn('Test Film');
        $film->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-01-01'));

        $publisher = $this->makePublisher(locales: ['en', 'de'], films: [$film]);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert: 2 index + 2 per-film = 4
        self::assertCount(4, $urls);
        $filmUrls = array_filter($urls, static fn($u) => $u->priority === 0.5);
        self::assertCount(2, $filmUrls);
        foreach ($filmUrls as $url) {
            self::assertSame('monthly', $url->changefreq);
        }
    }

    public function testReturnsEmptyWhenNoLocalesEnabled(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: [], films: []);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertSame([], $urls);
    }

    public function testReturnsEmptyWhenPluginInactive(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en'], films: [], pluginActive: false);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertSame([], $urls);
    }

    /**
     * @param array<string> $locales
     * @param Film[] $films
     */
    private function makePublisher(array $locales, array $films, bool $pluginActive = true): FilmclubSitemapPublisher
    {
        $filmService = $this->createStub(FilmService::class);
        $filmService->method('getList')->willReturn($films);

        $selectionService = $this->createStub(SelectionService::class);
        $selectionService->method('getSelectionsForFilm')->willReturn([]);

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredEnabledCodes')->willReturn($locales);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params = []) {
                $locale = $params['_locale'] ?? 'en';
                $id = isset($params['id']) ? '/' . $params['id'] : '';

                return "https://example.com/{$locale}{$id}/{$route}";
            },
        );

        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($pluginActive ? ['filmclub'] : []);

        return new FilmclubSitemapPublisher($filmService, $selectionService, $language, $urlGenerator, $pluginService);
    }
}
