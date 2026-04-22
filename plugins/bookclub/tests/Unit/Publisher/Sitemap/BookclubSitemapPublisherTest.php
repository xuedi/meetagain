<?php declare(strict_types=1);

namespace Plugin\Bookclub\Tests\Unit\Publisher\Sitemap;

use App\Service\Config\LanguageService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Publisher\Sitemap\BookclubSitemapPublisher;
use Plugin\Bookclub\Service\BookService;
use ReflectionClass;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class BookclubSitemapPublisherTest extends TestCase
{
    public function testEmitsIndexEvenWithNoBooks(): void
    {
        // Arrange
        $publisher = $this->makePublisher(locales: ['en'], approvedBooks: []);

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert: just the index entry
        self::assertCount(1, $urls);
        self::assertStringContainsString('app_plugin_bookclub', $urls[0]->loc);
        self::assertSame(0.7, $urls[0]->priority);
    }

    public function testEmitsBookDetailEntriesPerLocale(): void
    {
        // Arrange: 2 approved books, 2 locales
        $publisher = $this->makePublisher(
            locales: ['en', 'de'],
            approvedBooks: [
                $this->makeBook(1, new DateTimeImmutable('2026-03-01')),
                $this->makeBook(2, new DateTimeImmutable('2026-03-15')),
            ],
        );

        // Act
        $urls = $publisher->getSitemapUrls();

        // Assert: 1 index x 2 locales + 2 books x 2 locales = 6 entries
        self::assertCount(6, $urls);

        $bookUrls = array_filter($urls, static fn($u) => str_contains($u->loc, 'app_plugin_bookclub_book_show'));
        self::assertCount(4, $bookUrls);
        foreach ($bookUrls as $url) {
            self::assertSame(0.5, $url->priority);
            self::assertSame('monthly', $url->changefreq);
            self::assertCount(2, $url->alternates);
        }
    }

    public function testUsesBookCreatedAtAsLastmod(): void
    {
        // Arrange
        $publisher = $this->makePublisher(
            locales: ['en'],
            approvedBooks: [$this->makeBook(7, new DateTimeImmutable('2026-02-14'))],
        );

        // Act
        $urls = $publisher->getSitemapUrls();
        $bookUrl = current(array_filter($urls, static fn($u) => str_contains($u->loc, 'id=7'))) ?: null;

        // Assert
        self::assertNotNull($bookUrl);
        self::assertSame('2026-02-14', $bookUrl->lastmod?->format('Y-m-d'));
    }

    /**
     * @param array<string> $locales
     * @param array<Book> $approvedBooks
     */
    private function makePublisher(array $locales, array $approvedBooks): BookclubSitemapPublisher
    {
        $bookService = $this->createStub(BookService::class);
        $bookService->method('getApprovedList')->willReturn($approvedBooks);

        $language = $this->createStub(LanguageService::class);
        $language->method('getFilteredEnabledCodes')->willReturn($locales);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $params = []) {
                $locale = $params['_locale'] ?? 'en';
                $id = $params['id'] ?? null;
                if ($id !== null) {
                    return "https://example.com/{$locale}/{$route}?id={$id}";
                }

                return "https://example.com/{$locale}/{$route}";
            },
        );

        return new BookclubSitemapPublisher($bookService, $language, $urlGenerator);
    }

    private function makeBook(int $id, DateTimeImmutable $createdAt): Book
    {
        $reflection = new ReflectionClass(Book::class);
        $book = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('id')->setValue($book, $id);
        $reflection->getProperty('createdAt')->setValue($book, $createdAt);
        $reflection->getProperty('approved')->setValue($book, true);

        return $book;
    }
}
