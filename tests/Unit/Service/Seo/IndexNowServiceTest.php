<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Entity\Cms;
use App\Entity\Event;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Seo\IndexNowService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IndexNowServiceTest extends TestCase
{
    private function makeService(
        ?ConfigService $configService = null,
        ?AppStateService $appStateService = null,
        ?HttpClientInterface $httpClient = null,
        ?UrlGeneratorInterface $urlGenerator = null,
        ?LanguageService $languageService = null,
        ?EventRepository $eventRepository = null,
        ?CmsRepository $cmsRepository = null,
        ?LoggerInterface $logger = null,
    ): IndexNowService {
        $configStub = $configService ?? $this->createStub(ConfigService::class);
        $languageStub = $languageService ?? $this->createStub(LanguageService::class);
        $languageStub->method('getFilteredDefaultLocale')->willReturn('en');

        return new IndexNowService(
            configService: $configStub,
            appStateService: $appStateService ?? $this->createStub(AppStateService::class),
            httpClient: $httpClient ?? $this->createStub(HttpClientInterface::class),
            urlGenerator: $urlGenerator ?? $this->createStub(UrlGeneratorInterface::class),
            languageService: $languageStub,
            eventRepository: $eventRepository ?? $this->createStub(EventRepository::class),
            cmsRepository: $cmsRepository ?? $this->createStub(CmsRepository::class),
            logger: $logger ?? new NullLogger(),
        );
    }

    public function testGetOrCreateKeyReturnsExistingKey(): void
    {
        // Arrange
        $configStub = $this->createStub(ConfigService::class);
        $configStub->method('getString')->willReturn('existingkey1234567890abcdef123456');

        $service = $this->makeService(configService: $configStub);

        // Act
        $key = $service->getOrCreateKey();

        // Assert
        static::assertSame('existingkey1234567890abcdef123456', $key);
    }

    public function testGetOrCreateKeyGeneratesAndPersistsNewKeyWhenAbsent(): void
    {
        // Arrange
        $configMock = $this->createMock(ConfigService::class);
        $configMock->method('getString')->willReturn('');
        $configMock
            ->expects($this->once())
            ->method('setString')
            ->with('indexnow_key', static::matchesRegularExpression('/^[a-f0-9]{32}$/'));

        $service = $this->makeService(configService: $configMock);

        // Act
        $key = $service->getOrCreateKey();

        // Assert: key is a 32-char hex string
        static::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);
    }

    public function testGetOrCreateKeyReturnsSameKeyOnConsecutiveCalls(): void
    {
        // Arrange: first call returns empty (key generated), subsequent calls return the generated key
        $generatedKey = null;
        $configStub = $this->createStub(ConfigService::class);
        $configStub
            ->method('getString')
            ->willReturnCallback(static function () use (&$generatedKey): string {
                return $generatedKey ?? '';
            });
        $configStub
            ->method('setString')
            ->willReturnCallback(static function (string $name, string $value) use (&$generatedKey): void {
                $generatedKey = $value;
            });

        $service = $this->makeService(configService: $configStub);

        // Act
        $key1 = $service->getOrCreateKey();
        $key2 = $service->getOrCreateKey();

        // Assert: both calls return the same key
        static::assertSame($key1, $key2);
    }

    public function testGetUrlListIncludesStaticCmsAndEventUrls(): void
    {
        // Arrange: CMS page with a slug
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getSlug')->willReturn('about');

        $cmsRepositoryStub = $this->createStub(CmsRepository::class);
        $cmsRepositoryStub->method('findPublished')->willReturn([$cmsPage]);

        // Arrange: event with an ID
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(42);

        $eventRepositoryStub = $this->createStub(EventRepository::class);
        $eventRepositoryStub->method('findForSitemap')->willReturn([$event]);

        // Arrange: URL generator returns distinct URLs per route
        $urlGeneratorStub = $this->createStub(UrlGeneratorInterface::class);
        $urlGeneratorStub
            ->method('generate')
            ->willReturnCallback(static fn(string $route, array $params): string => match ($route) {
                'app_default' => 'https://example.com/en/',
                'app_event' => 'https://example.com/en/events',
                'app_member' => 'https://example.com/en/members',
                'app_catch_all' => 'https://example.com/en/about',
                'app_event_details' => 'https://example.com/en/events/42',
                default => 'https://example.com/unknown',
            });

        $service = $this->makeService(
            urlGenerator: $urlGeneratorStub,
            eventRepository: $eventRepositoryStub,
            cmsRepository: $cmsRepositoryStub,
        );

        // Act
        $urls = $service->getUrlList();

        // Assert: all five expected URLs are present
        static::assertContains('https://example.com/en/', $urls);
        static::assertContains('https://example.com/en/events', $urls);
        static::assertContains('https://example.com/en/members', $urls);
        static::assertContains('https://example.com/en/about', $urls);
        static::assertContains('https://example.com/en/events/42', $urls);
        static::assertCount(5, $urls);
    }

    public function testGetUrlListSkipsCmsPageWithNullSlug(): void
    {
        // Arrange: CMS page without a slug
        $cmsPage = $this->createStub(Cms::class);
        $cmsPage->method('getSlug')->willReturn(null);

        $cmsRepositoryStub = $this->createStub(CmsRepository::class);
        $cmsRepositoryStub->method('findPublished')->willReturn([$cmsPage]);

        $eventRepositoryStub = $this->createStub(EventRepository::class);
        $eventRepositoryStub->method('findForSitemap')->willReturn([]);

        $urlGeneratorStub = $this->createStub(UrlGeneratorInterface::class);
        $urlGeneratorStub->method('generate')->willReturn('https://example.com/page');

        $service = $this->makeService(
            urlGenerator: $urlGeneratorStub,
            eventRepository: $eventRepositoryStub,
            cmsRepository: $cmsRepositoryStub,
        );

        // Act
        $urls = $service->getUrlList();

        // Assert: only 3 static routes, no CMS URL added
        static::assertCount(3, $urls);
    }

    public function testGetUrlListSkipsEventWithNullId(): void
    {
        // Arrange: event without an ID
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(null);

        $eventRepositoryStub = $this->createStub(EventRepository::class);
        $eventRepositoryStub->method('findForSitemap')->willReturn([$event]);

        $cmsRepositoryStub = $this->createStub(CmsRepository::class);
        $cmsRepositoryStub->method('findPublished')->willReturn([]);

        $urlGeneratorStub = $this->createStub(UrlGeneratorInterface::class);
        $urlGeneratorStub->method('generate')->willReturn('https://example.com/page');

        $service = $this->makeService(
            urlGenerator: $urlGeneratorStub,
            eventRepository: $eventRepositoryStub,
            cmsRepository: $cmsRepositoryStub,
        );

        // Act
        $urls = $service->getUrlList();

        // Assert: only 3 static routes, no event URL added
        static::assertCount(3, $urls);
    }

    public function testSubmitPostsCorrectPayloadAndReturnsStatus(): void
    {
        // Arrange
        $configStub = $this->createStub(ConfigService::class);
        $configStub->method('getString')->willReturn('abc123def456abc1');
        $configStub->method('getHost')->willReturn('https://example.com');

        $responseMock = $this->createStub(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);

        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $httpClientMock
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.indexnow.org/IndexNow',
                static::callback(
                    static fn(array $options): bool => (
                        $options['json']['host'] === 'example.com'
                        && $options['json']['key'] === 'abc123def456abc1'
                        && str_contains($options['json']['keyLocation'], 'abc123def456abc1.txt')
                        && is_array($options['json']['urlList'])
                    ),
                ),
            )
            ->willReturn($responseMock);

        $urlGeneratorStub = $this->createStub(UrlGeneratorInterface::class);
        $urlGeneratorStub->method('generate')->willReturn('https://example.com/en/');

        $cmsRepositoryStub = $this->createStub(CmsRepository::class);
        $cmsRepositoryStub->method('findPublished')->willReturn([]);

        $eventRepositoryStub = $this->createStub(EventRepository::class);
        $eventRepositoryStub->method('findForSitemap')->willReturn([]);

        $service = $this->makeService(
            configService: $configStub,
            httpClient: $httpClientMock,
            urlGenerator: $urlGeneratorStub,
            eventRepository: $eventRepositoryStub,
            cmsRepository: $cmsRepositoryStub,
        );

        // Act
        $result = $service->submit();

        // Assert
        static::assertSame(200, $result['status']);
        static::assertSame('example.com', $result['host']);
    }

    public function testGetLastSubmittedAtReturnsNullWhenNeverSubmitted(): void
    {
        // Arrange
        $appStateStub = $this->createStub(AppStateService::class);
        $appStateStub->method('get')->willReturn(null);

        $service = $this->makeService(appStateService: $appStateStub);

        // Act
        $result = $service->getLastSubmittedAt();

        // Assert
        static::assertNull($result);
    }

    public function testGetLastSubmittedAtReturnsDateTimeFromStoredValue(): void
    {
        // Arrange
        $timestamp = '2026-04-11T10:00:00+00:00';
        $appStateStub = $this->createStub(AppStateService::class);
        $appStateStub->method('get')->willReturn($timestamp);

        $service = $this->makeService(appStateService: $appStateStub);

        // Act
        $result = $service->getLastSubmittedAt();

        // Assert
        static::assertInstanceOf(DateTimeImmutable::class, $result);
        static::assertSame('2026-04-11T10:00:00+00:00', $result->format(DateTimeImmutable::ATOM));
    }

    public function testRecordSubmissionPersistsIso8601Timestamp(): void
    {
        // Arrange
        $appStateMock = $this->createMock(AppStateService::class);
        $appStateMock
            ->expects($this->once())
            ->method('set')
            ->with(
                'seo_indexnow_last_submit',
                static::matchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/'),
            );

        $service = $this->makeService(appStateService: $appStateMock);

        // Act
        $service->recordSubmission();

        // Assert: expectation verified automatically
    }
}
