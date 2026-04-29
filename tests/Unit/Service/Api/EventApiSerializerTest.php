<?php declare(strict_types=1);

namespace Tests\Unit\Service\Api;

use App\Entity\Event;
use App\Entity\Image;
use App\Entity\Location;
use App\Service\Api\EventApiSerializer;
use DateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EventApiSerializerTest extends TestCase
{
    public function testSummaryShapeWithAllFields(): void
    {
        // Arrange
        $event = $this->makeEvent(id: 42, start: new DateTime('2026-05-01T10:00:00+00:00'), image: $this->makeImage('abc123'));
        $serializer = new EventApiSerializer($this->makeUrlGenerator());

        // Act
        $summary = $serializer->toSummary($event, 'en', 'https://example.com');

        // Assert
        self::assertSame(42, $summary['id']);
        self::assertSame('https://example.com/en/event/42', $summary['url']);
        self::assertSame('https://example.com/images/thumbnails/abc123_600x400.webp', $summary['previewImageUrl']);
        self::assertSame('2026-05-01T10:00:00+00:00', $summary['start']);
    }

    public function testPreviewImageUrlIsNullWhenImageMissing(): void
    {
        // Arrange
        $event = $this->makeEvent(id: 1, start: new DateTime('2026-05-01T10:00:00+00:00'), image: null);
        $serializer = new EventApiSerializer($this->makeUrlGenerator());

        // Act
        $summary = $serializer->toSummary($event, 'en', 'https://example.com');

        // Assert
        self::assertNull($summary['previewImageUrl']);
    }

    public function testDetailIncludesDescription(): void
    {
        // Arrange
        $event = $this->makeEvent(id: 1, start: new DateTime('2026-05-01T10:00:00+00:00'), image: null);
        $serializer = new EventApiSerializer($this->makeUrlGenerator());

        // Act
        $detail = $serializer->toDetail($event, 'en', 'https://example.com');

        // Assert
        self::assertArrayHasKey('description', $detail);
    }

    private function makeUrlGenerator(): UrlGeneratorInterface
    {
        $gen = $this->createStub(UrlGeneratorInterface::class);
        $gen->method('generate')->willReturnCallback(
            static function (string $route, array $params) {
                $locale = $params['_locale'] ?? 'en';
                $id = $params['id'] ?? 0;

                return "/{$locale}/event/{$id}";
            },
        );

        return $gen;
    }

    private function makeImage(string $hash): Image
    {
        $image = new Image();
        $image->setHash($hash);
        $image->setMimeType('image/jpeg');
        $image->setExtension('jpg');
        $image->setSize(0);

        return $image;
    }

    private function makeEvent(int $id, \DateTimeInterface $start, ?Image $image): Event
    {
        $event = new Event();

        $reflection = new \ReflectionClass(Event::class);
        $reflection->getProperty('id')->setValue($event, $id);
        $reflection->getProperty('start')->setValue($event, $start);
        $reflection->getProperty('previewImage')->setValue($event, $image);

        $location = new Location();
        $location->setName('Somewhere');
        $location->setStreet('X');
        $location->setPostcode('00000');
        $location->setCity('Town');
        $reflection->getProperty('location')->setValue($event, $location);

        return $event;
    }
}
