<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Publisher\Sitemap;

use App\Service\Config\LanguageService;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Publisher\Sitemap\FilmclubSitemapPublisher;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FilmclubSitemapPublisherTest extends TestCase
{
    public function testEmitsFilmlistForEachLocale(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en', 'de']);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertCount(2, $urls);
        foreach ($urls as $url) {
            self::assertSame(0.7, $url->priority);
            self::assertSame('weekly', $url->changefreq);
            self::assertCount(2, $url->alternates);
            self::assertStringContainsString('app_filmclub_filmlist', $url->loc);
        }
    }

    public function testReturnsEmptyWhenNoLocalesEnabled(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: []);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertSame([], $urls);
    }

    public function testReturnsEmptyWhenPluginInactive(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en'], pluginActive: false);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert
        self::assertSame([], $urls);
    }

    /**
     * @param array<string> $locales
     */
    private function makePublisher(array $locales, bool $pluginActive = true): FilmclubSitemapPublisher
    {
        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredEnabledCodes')->willReturn($locales);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params = []) {
                $locale = $params['_locale'] ?? 'en';

                return "https://example.com/{$locale}/{$route}";
            },
        );

        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($pluginActive ? ['filmclub'] : []);

        return new FilmclubSitemapPublisher($language, $urlGenerator, $pluginService);
    }
}
