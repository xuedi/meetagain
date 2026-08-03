<?php declare(strict_types=1);

namespace Tests\Unit\Service\Event;

use App\Entity\Event;
use App\Entity\EventTranslation;
use App\Entity\Location;
use App\Publisher\UrlOwner\UrlOwnerProviderInterface;
use App\Service\Config\ConfigService;
use App\Service\Event\ShareService;
use App\Service\Seo\UrlOwnerService;
use App\ValueObject\ShareLink;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Unit\Stubs\EventStub;

class ShareServiceTest extends TestCase
{
    public function testTheShareUrlPointsAtTheViewedOccurrenceOnItsOwnerHost(): void
    {
        // Arrange
        $service = $this->makeService(claimedEventId: 17);
        $event = $this->makeEvent(17, 'Board games night');

        // Act
        $sheet = $service->buildSheet($event, 'en');

        // Assert
        self::assertSame('https://dragon.meetagain.org/en/event/17', $sheet->shareUrl);
    }

    public function testAnUnclaimedEventIsSharedOnTheConfiguredHost(): void
    {
        // Arrange
        $service = $this->makeService(claimedEventId: 17);
        $event = $this->makeEvent(18, 'Board games night');

        // Act
        $sheet = $service->buildSheet($event, 'en');

        // Assert
        self::assertSame('https://meetagain.org/en/event/18', $sheet->shareUrl);
    }

    public function testShareTargetsEncodeTheTitleAndTheUrl(): void
    {
        // Arrange
        $service = $this->makeService();
        $event = $this->makeEvent(18, 'Brot & Spiele: Grünkohl');

        // Act
        $targets = $this->indexByKey($service->buildSheet($event, 'en')->shareTargets);

        // Assert
        self::assertSame(
            'https://wa.me/?text=Brot%20%26%20Spiele%3A%20Gr%C3%BCnkohl%20https%3A%2F%2Fmeetagain.org%2Fen%2Fevent%2F18',
            $targets['whatsapp']->url,
        );
        self::assertSame(
            'https://t.me/share/url?url=https%3A%2F%2Fmeetagain.org%2Fen%2Fevent%2F18&text=Brot%20%26%20Spiele%3A%20Gr%C3%BCnkohl',
            $targets['telegram']->url,
        );
        self::assertSame(
            'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fmeetagain.org%2Fen%2Fevent%2F18',
            $targets['facebook']->url,
        );
    }

    public function testTheMailTargetCarriesTheTeaserAndTheUrlInItsBody(): void
    {
        // Arrange
        $service = $this->makeService();
        $event = $this->makeEvent(18, 'Board games night', 'Bring a game.');

        // Act
        $targets = $this->indexByKey($service->buildSheet($event, 'en')->shareTargets);

        // Assert
        self::assertSame(
            'mailto:?subject=Board%20games%20night&body=Bring%20a%20game.%0A%0Ahttps%3A%2F%2Fmeetagain.org%2Fen%2Fevent%2F18',
            $targets['email']->url,
        );
    }

    public function testTheTeaserIsReducedToPlainTextForSharing(): void
    {
        // Arrange
        $service = $this->makeService();
        $event = $this->makeEvent(18, 'Board games night', "<p>Bring   a game.</p>\n<p>Snacks &amp; drinks</p>");

        // Act
        $sheet = $service->buildSheet($event, 'en');

        // Assert
        self::assertSame('Bring a game. Snacks & drinks', $sheet->teaser);
    }

    public function testALongTeaserIsTruncated(): void
    {
        // Arrange
        $service = $this->makeService();
        $event = $this->makeEvent(18, 'Board games night', str_repeat('a', 450));

        // Act
        $sheet = $service->buildSheet($event, 'en');

        // Assert
        self::assertSame(str_repeat('a', 400) . '...', $sheet->teaser);
    }

    public function testMapLinksUseTheCoordinatesWhenTheLocationHasThem(): void
    {
        // Arrange
        $service = $this->makeService();
        $event = $this->makeEvent(18, 'Board games night');
        $event->setLocation($this->makeLocation('52.5162', '13.3777'));

        // Act
        $links = $this->indexByKey($service->buildSheet($event, 'en')->mapLinks);

        // Assert
        self::assertSame(
            'https://www.openstreetmap.org/?mlat=52.5162&mlon=13.3777#map=17/52.5162/13.3777',
            $links['osm']->url,
        );
        self::assertSame(
            'https://www.google.com/maps/dir/?api=1&destination=52.5162%2C13.3777',
            $links['directions']->url,
        );
    }

    public function testMapLinksFallBackToTheAddressWithoutCoordinates(): void
    {
        // Arrange
        $service = $this->makeService();
        $event = $this->makeEvent(18, 'Board games night');
        $event->setLocation($this->makeLocation(null, null));

        // Act
        $links = $this->indexByKey($service->buildSheet($event, 'en')->mapLinks);

        // Assert
        self::assertSame(
            'https://www.openstreetmap.org/search?query=Cafe%20Central%2C%20Hauptstr.%201%2C%2010115%20Berlin',
            $links['osm']->url,
        );
        self::assertSame(
            'https://www.google.com/maps/dir/?api=1&destination=Cafe%20Central%2C%20Hauptstr.%201%2C%2010115%20Berlin',
            $links['directions']->url,
        );
    }

    public function testAnEventWithoutALocationHasNoMapLinks(): void
    {
        // Arrange
        $service = $this->makeService();
        $event = $this->makeEvent(18, 'Board games night');

        // Act
        $sheet = $service->buildSheet($event, 'en');

        // Assert
        self::assertSame([], $sheet->mapLinks);
    }

    public function testTheSheetCarriesAnInlineQrCodeAndADownloadUrl(): void
    {
        // Arrange
        $service = $this->makeService();
        $event = $this->makeEvent(18, 'Board games night');

        // Act
        $sheet = $service->buildSheet($event, 'en');

        // Assert
        self::assertStringStartsWith('<svg', $sheet->qrSvg);
        self::assertSame('/en/event/18/share/qr.png', $sheet->qrPngUrl);
    }

    private function makeService(?int $claimedEventId = null): ShareService
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getHost')->willReturn('https://meetagain.org');

        $provider = $this->createStub(UrlOwnerProviderInterface::class);
        $provider->method('getOwnerHost')->willReturnCallback(
            static fn(string $route, array $parameters): ?string => ($parameters['id'] ?? null) === $claimedEventId
                ? 'https://dragon.meetagain.org'
                : null,
        );

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn(string $route, array $parameters): string => $route === 'app_event_share_qr'
                ? sprintf('/%s/event/%d/share/qr.png', $parameters['_locale'] ?? 'en', $parameters['id'])
                : sprintf('/%s/event/%d', $parameters['_locale'] ?? 'en', $parameters['id']),
        );

        return new ShareService(new UrlOwnerService($config, [$provider]), $urlGenerator);
    }

    private function makeEvent(int $id, string $title, string $teaser = ''): Event
    {
        $translation = new EventTranslation();
        $translation->setLanguage('en');
        $translation->setTitle($title);
        $translation->setTeaser($teaser);

        return new EventStub()->setId($id)->addTranslation($translation);
    }

    private function makeLocation(?string $latitude, ?string $longitude): Location
    {
        $location = new Location();
        $location->setName('Cafe Central');
        $location->setStreet('Hauptstr. 1');
        $location->setPostcode('10115');
        $location->setCity('Berlin');
        $location->setLatitude($latitude);
        $location->setLongitude($longitude);

        return $location;
    }

    /**
     * @param list<ShareLink> $links
     * @return array<string, ShareLink>
     */
    private function indexByKey(array $links): array
    {
        $indexed = [];
        foreach ($links as $link) {
            $indexed[$link->key] = $link;
        }

        return $indexed;
    }
}
