<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\ActivityPub;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Image;
use App\Entity\Location as Venue;
use App\Service\Config\ConfigService as CoreConfigService;
use App\Service\Config\LanguageService;
use App\Service\Seo\UrlOwnerService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\ActivityPub\EventObject;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Enum\Content;
use Plugin\Fediverse\Enum\Location;
use Plugin\Fediverse\Repository\PublicationRepository;
use Plugin\Fediverse\ValueObject\Config;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventObjectTest extends TestCase
{
    private const string PAGE = 'https://events.example/en/event/7';

    public function testAnEventLinksBackToItsPageAndJoinsThere(): void
    {
        // Act
        $document = $this->build($this->event(), new Config());

        // Assert
        static::assertSame('Event', $document['type']);
        static::assertSame('Spring open', $document['name']);
        static::assertSame(['en' => 'Spring open', 'de' => 'Frühlingsturnier'], $document['nameMap']);
        static::assertSame(self::PAGE, $document['url']);
        static::assertSame('external', $document['joinMode'], 'Mobilizon reads the compacted term, not a prefixed key');
        static::assertSame(self::PAGE, $document['externalParticipationUrl']);
        static::assertSame('2026-12-05T14:00:00+00:00', $document['startTime']);
        static::assertSame('2026-12-05T18:00:00+00:00', $document['endTime']);
        static::assertSame('CONFIRMED', $document['ical:status']);
        static::assertSame('https://events.example/fediverse/@chess', $document['attributedTo']);
        static::assertSame([EventObject::PUBLIC], $document['to']);
    }

    public function testTheSummarySettingSharesTheTeaserPerLocale(): void
    {
        // Act
        $document = $this->build($this->event(), new Config()->setContent(Content::Summary));

        // Assert
        static::assertSame('<p>Seven rounds &amp; a prize.</p>', $document['content']);
        static::assertSame(['en' => '<p>Seven rounds &amp; a prize.</p>', 'de' => '<p>Sieben Runden.</p>'], $document['contentMap']);
        static::assertSame($document['contentMap'], $document['summaryMap'], 'Mastodon shows an event\'s summary, not its content');
    }

    public function testWithoutATeaserTheSummaryIsTheShortenedDescriptionText(): void
    {
        // Arrange
        $event = $this->event(teaser: '', description: '<p>' . str_repeat('word ', 200) . '</p>');

        // Act
        $document = $this->build($event, new Config()->setContent(Content::Summary));

        // Assert
        static::assertStringNotContainsString('<p>word', substr($document['content'], 3));
        static::assertSame(EventObject::SUMMARY_MAX, mb_strlen(strip_tags(html_entity_decode($document['content']))));
        static::assertStringEndsWith('…</p>', $document['content']);
    }

    public function testTheTitleSettingSharesNoText(): void
    {
        // Act
        $document = $this->build($this->event(), new Config()->setContent(Content::Title));

        // Assert
        static::assertArrayNotHasKey('content', $document);
        static::assertArrayNotHasKey('contentMap', $document);
        static::assertArrayNotHasKey('summary', $document);
    }

    public function testTheFullSettingSharesTheDescription(): void
    {
        // Act
        $document = $this->build($this->event(), new Config()->setContent(Content::Full));

        // Assert
        static::assertSame('<p>The full <b>programme</b>.</p>', $document['content']);
        static::assertSame('<p>Seven rounds &amp; a prize.</p>', $document['summary']);
    }

    public function testNoLocationSharesNoPlace(): void
    {
        // Act
        $document = $this->build($this->event(), new Config()->setLocation(Location::None));

        // Assert
        static::assertArrayNotHasKey('location', $document);
    }

    public function testTheCitySettingSharesTheCityOnly(): void
    {
        // Act
        $document = $this->build($this->event(), new Config()->setLocation(Location::City));

        // Assert
        static::assertSame(
            ['type' => 'Place', 'name' => 'Leipzig', 'address' => ['type' => 'PostalAddress', 'addressLocality' => 'Leipzig']],
            $document['location'],
        );
    }

    public function testTheAddressSettingSharesTheVenueAndItsPosition(): void
    {
        // Act
        $document = $this->build($this->event(), new Config()->setLocation(Location::Address));

        // Assert
        static::assertSame('Café Brett', $document['location']['name']);
        static::assertSame('Markt 1', $document['location']['address']['streetAddress']);
        static::assertSame('04109', $document['location']['address']['postalCode']);
        static::assertSame(51.34, $document['location']['latitude']);
    }

    public function testTheImageIsSharedOnlyWhenAllowed(): void
    {
        // Act
        $shown = $this->build($this->event(), new Config()->setImage(true));
        $hidden = $this->build($this->event(), new Config()->setImage(false));

        // Assert
        static::assertSame('https://events.example/images/thumbnails/abc123_600x400.webp', $shown['attachment'][0]['url']);
        static::assertArrayNotHasKey('attachment', $hidden);
    }

    public function testACancelledEventSaysSo(): void
    {
        // Arrange
        $event = $this->event()->setCanceled(true);

        // Act
        $document = $this->build($event, new Config()->setContent(Content::Title));

        // Assert
        static::assertSame('CANCELLED', $document['ical:status']);
        static::assertSame('<p>en:events.notification_event_canceled</p>', $document['summary'], 'Mastodon ignores ical:status and shows the summary');
        static::assertArrayNotHasKey('content', $document);
    }

    /** @return array<string, mixed> */
    private function build(Event $event, Config $config): array
    {
        $actor = $this->createStub(Actor::class);
        $actor->method('id')->willReturn('https://events.example/fediverse/@chess');
        $actor->method('accountOrigin')->willReturn('https://events.example');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/en/event/7');

        $languages = $this->createStub(LanguageService::class);
        $languages->method('getFilteredDefaultLocale')->willReturn('en');
        $languages->method('getFilteredEnabledCodes')->willReturn(['en', 'de', 'fr']);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn(string $key, array $parameters, ?string $domain, ?string $locale): string => $locale . ':' . $key);

        $coreConfig = $this->createStub(CoreConfigService::class);
        $coreConfig->method('getHost')->willReturn('https://events.example');

        $eventObject = new EventObject(
            $actor,
            $urlGenerator,
            $languages,
            new UrlOwnerService($coreConfig, []),
            $this->createStub(PublicationRepository::class),
            $translator,
        );

        return $eventObject->build($event, new Account('7', 'chess', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01')), $config);
    }

    private function event(string $teaser = 'Seven rounds & a prize.', string $description = '<p>The full <b>programme</b>.</p>'): Event
    {
        $venue = new Venue()
            ->setName('Café Brett')
            ->setStreet('Markt 1')
            ->setPostcode('04109')
            ->setCity('Leipzig')
            ->setLatitude('51.34')
            ->setLongitude('12.37');

        return new Event()
            ->setStart(new DateTimeImmutable('2026-12-05T14:00:00+00:00'))
            ->setStop(new DateTimeImmutable('2026-12-05T18:00:00+00:00'))
            ->setLocation($venue)
            ->setPreviewImage(new Image()->setHash('abc123'))
            ->addTranslation(
                new EventTranslation()
                    ->setLanguage('en')
                    ->setTitle('Spring open')
                    ->setTeaser($teaser)
                    ->setDescription($description),
            )
            ->addTranslation(
                new EventTranslation()
                    ->setLanguage('de')
                    ->setTitle('Frühlingsturnier')
                    ->setTeaser('Sieben Runden.')
                    ->setDescription('<p>Alles.</p>'),
            );
    }
}
