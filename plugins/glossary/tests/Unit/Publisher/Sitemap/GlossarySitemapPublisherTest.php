<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Publisher\Sitemap;

use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Publisher\Sitemap\GlossarySitemapPublisher;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GlossarySitemapPublisherTest extends TestCase
{
    public function testEmitsGlossaryIndexForEachLocale(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en', 'de', 'zh']);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert: one entry per locale, each with all-locale alternates and weekly priority 0.7
        self::assertCount(3, $urls);
        foreach ($urls as $url) {
            self::assertSame(0.7, $url->priority);
            self::assertSame('weekly', $url->changefreq);
            self::assertCount(3, $url->alternates);
            self::assertStringContainsString('app_plugin_glossary', $url->loc);
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

    /**
     * @param array<string> $locales
     */
    private function makePublisher(array $locales): GlossarySitemapPublisher
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

        return new GlossarySitemapPublisher($language, $urlGenerator);
    }
}
