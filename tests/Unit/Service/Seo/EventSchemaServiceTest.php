<?php declare(strict_types=1);

namespace Tests\Unit\Service\Seo;

use App\Entity\Event;
use App\Entity\Host;
use App\Entity\Image;
use App\Publisher\OrganizationSchema\OrganizationSchemaProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Seo\EventSchemaService;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventSchemaServiceTest extends TestCase
{
    private const string CANONICAL_URL = 'https://meetagain.org/en/events/foo';
    private const string PLATFORM_HOST = 'https://meetagain.org';

    public function testFullyPopulatedEventEmitsAllFiveRecommendedFields(): void
    {
        // Arrange
        $event = $this->makeEvent(
            title: 'Coffee chat',
            teaser: 'Meet up for coffee in the park',
            previewImageHash: 'abc123',
            hostName: 'Berlin Crew',
        );
        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert
        static::assertArrayHasKey('description', $schema);
        static::assertNotEmpty($schema['description']);
        static::assertArrayHasKey('image', $schema);
        static::assertNotEmpty($schema['image']);
        static::assertArrayHasKey('eventStatus', $schema);
        static::assertNotEmpty($schema['eventStatus']);
        static::assertArrayHasKey('organizer', $schema);
        static::assertNotEmpty($schema['organizer']);
        static::assertArrayHasKey('performer', $schema);
        static::assertNotEmpty($schema['performer']);
    }

    public function testMinimalEventStillEmitsAllFiveRecommendedFieldsViaFallbacks(): void
    {
        // Arrange: no teaser, no description, no preview image, no host
        $event = $this->makeEvent(title: 'Coffee chat');
        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert: every field falls back to a non-empty value
        static::assertSame('Coffee chat', $schema['description'], 'description falls back to title');
        static::assertSame(
            [self::PLATFORM_HOST . '/images/locations/default.jpg'],
            $schema['image'],
            'image falls back to default static asset',
        );
        static::assertSame('https://schema.org/EventScheduled', $schema['eventStatus']);
        static::assertSame('MeetAgain', $schema['organizer']['name'], 'organizer falls back to platform org');
        static::assertSame($schema['organizer'], $schema['performer'], 'performer mirrors organizer when no host');
    }

    public function testEventStatusReflectsCancellation(): void
    {
        // Arrange
        $event = $this->makeEvent(title: 'Coffee chat', canceled: true);
        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert
        static::assertSame('https://schema.org/EventCancelled', $schema['eventStatus']);
    }

    public function testOrganizerUsesEventHostWhenPresent(): void
    {
        // Arrange
        $event = $this->makeEvent(title: 'Coffee chat', hostName: 'Berlin Crew');
        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert
        static::assertSame('Organization', $schema['organizer']['@type']);
        static::assertSame('Berlin Crew', $schema['organizer']['name']);
        static::assertSame($schema['organizer'], $schema['performer'], 'performer mirrors event-host organizer');
    }

    public function testOrganizerEmitsArrayWhenMultipleHosts(): void
    {
        // Arrange
        $hostA = new Host();
        $hostA->setName('Berlin Crew');
        $hostB = new Host();
        $hostB->setName('Munich Crew');
        $event = $this->makeEvent(title: 'Coffee chat', hosts: new ArrayCollection([$hostA, $hostB]));

        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert
        static::assertCount(2, $schema['organizer']);
        static::assertSame('Berlin Crew', $schema['organizer'][0]['name']);
        static::assertSame('Munich Crew', $schema['organizer'][1]['name']);
    }

    public function testImagePrefersPreviewImageWhenAvailable(): void
    {
        // Arrange
        $event = $this->makeEvent(title: 'Coffee chat', previewImageHash: 'abc123');
        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert
        static::assertSame(
            [self::PLATFORM_HOST . '/images/thumbnails/abc123_600x400.webp'],
            $schema['image'],
        );
    }

    public function testImageUsesProviderLogoWhenNoPreviewImage(): void
    {
        // Arrange
        $event = $this->makeEvent(title: 'Coffee chat');
        $logoUrl = 'https://cdn.example.com/logo.png';
        $providerOrg = [
            '@type' => 'Organization',
            'name' => 'Sponsor Org',
            'url' => 'https://example.com',
            'logo' => $logoUrl,
        ];
        $subject = $this->makeService(providerOrg: $providerOrg);

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert
        static::assertSame([$logoUrl], $schema['image']);
    }

    public function testDescriptionPrefersTeaserOverDescription(): void
    {
        // Arrange
        $event = $this->makeEvent(
            title: 'Coffee chat',
            teaser: 'Short teaser text',
            description: '<p>Long description body</p>',
        );
        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert
        static::assertSame('Short teaser text', $schema['description']);
    }

    public function testDescriptionFallsBackToStrippedDescription(): void
    {
        // Arrange
        $event = $this->makeEvent(
            title: 'Coffee chat',
            description: '<p>Long description body</p>',
        );
        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, 'en');

        // Assert
        static::assertSame('Long description body', $schema['description']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function localeProvider(): array
    {
        return [
            'english' => ['en'],
            'german' => ['de'],
            'chinese' => ['zh'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function testTitleIsLocaleSpecific(string $locale): void
    {
        // Arrange
        $titles = ['en' => 'Coffee chat', 'de' => 'Kaffeerunde', 'zh' => '咖啡聚会'];
        $event = $this->createStub(Event::class);
        $event->method('getStart')->willReturn(new DateTimeImmutable('2026-05-01T10:00:00+00:00'));
        $event->method('getStop')->willReturn(null);
        $event->method('isCanceled')->willReturn(false);
        $event->method('getPreviewImage')->willReturn(null);
        $event->method('getLocation')->willReturn(null);
        $event->method('getHost')->willReturn(new ArrayCollection());
        $event->method('getTitle')->willReturnCallback(static fn(string $l): string => $titles[$l] ?? '');
        $event->method('getTeaser')->willReturn('');
        $event->method('getDescription')->willReturn('');

        $subject = $this->makeService();

        // Act
        $schema = $subject->buildSchema($event, self::CANONICAL_URL, $locale);

        // Assert: name and the description fallback both reflect the locale-specific title
        static::assertSame($titles[$locale], $schema['name']);
        static::assertSame($titles[$locale], $schema['description']);
    }

    /**
     * @param array<string, mixed>|null $providerOrg
     */
    private function makeService(?array $providerOrg = null): EventSchemaService
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getHost')->willReturn(self::PLATFORM_HOST);

        $providers = [];
        if ($providerOrg !== null) {
            $provider = $this->createStub(OrganizationSchemaProviderInterface::class);
            $provider->method('getOrganizationSchema')->willReturn($providerOrg);
            $providers[] = $provider;
        }

        return new EventSchemaService($configService, $providers);
    }

    private function makeEvent(
        string $title,
        string $teaser = '',
        string $description = '',
        ?string $previewImageHash = null,
        ?string $hostName = null,
        bool $canceled = false,
        ?ArrayCollection $hosts = null,
    ): Event {
        $event = $this->createStub(Event::class);
        $event->method('getStart')->willReturn(new DateTimeImmutable('2026-05-01T10:00:00+00:00'));
        $event->method('getStop')->willReturn(null);
        $event->method('isCanceled')->willReturn($canceled);
        $event->method('getLocation')->willReturn(null);
        $event->method('getTitle')->willReturn($title);
        $event->method('getTeaser')->willReturn($teaser);
        $event->method('getDescription')->willReturn($description);

        if ($previewImageHash !== null) {
            $image = $this->createStub(Image::class);
            $image->method('getHash')->willReturn($previewImageHash);
            $event->method('getPreviewImage')->willReturn($image);
        } else {
            $event->method('getPreviewImage')->willReturn(null);
        }

        if ($hosts === null) {
            $hosts = new ArrayCollection();
            if ($hostName !== null) {
                $host = new Host();
                $host->setName($hostName);
                $hosts->add($host);
            }
        }
        $event->method('getHost')->willReturn($hosts);

        return $event;
    }
}
