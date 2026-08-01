<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Service\ExifService;
use Psr\Log\NullLogger;

class ExifServiceTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures';

    public function testExtractsTheCuratedFieldSetFromAPhotograph(): void
    {
        // Arrange
        $service = new ExifService(new NullLogger());

        // Act
        $meta = $service->extract(self::FIXTURE_DIR . '/with-exif.jpg');

        // Assert
        static::assertSame([
            'make' => 'FUJIFILM',
            'model' => 'X-T5',
            'lens' => 'XF23mmF1.4 R LM WR',
            'exposureTime' => '1/800',
            'fNumber' => 2.8,
            'iso' => 160,
            'focalLength' => 23.0,
            'focalLength35' => 35,
            'exposureBias' => -0.33,
            'flash' => false,
            'whiteBalance' => 0,
            'meteringMode' => 5,
            'takenAt' => '2026-04-18 07:42:11',
            'width' => 160,
            'height' => 120,
        ], $meta);
    }

    public function testCarriesNoLocationData(): void
    {
        // Arrange
        $service = new ExifService(new NullLogger());

        // Act
        $meta = $service->extract(self::FIXTURE_DIR . '/with-exif.jpg') ?? [];

        // Assert
        foreach (array_keys($meta) as $key) {
            static::assertStringNotContainsStringIgnoringCase('gps', (string) $key);
            static::assertStringNotContainsStringIgnoringCase('latitude', (string) $key);
            static::assertStringNotContainsStringIgnoringCase('longitude', (string) $key);
        }
    }

    #[DataProvider('unreadableFiles')]
    public function testYieldsNullMeta(string $file): void
    {
        // Arrange
        $service = new ExifService(new NullLogger());

        // Act
        $meta = $service->extract(self::FIXTURE_DIR . '/' . $file);

        // Assert
        static::assertNull($meta);
    }

    /** @return iterable<string, array{0: string}> */
    public static function unreadableFiles(): iterable
    {
        yield 'a stripped PNG has no EXIF block' => ['no-exif.png'];
        yield 'a missing file cannot be opened' => ['does-not-exist.jpg'];
    }

    public function testResolvesTheTakenAtStampFromMeta(): void
    {
        // Arrange
        $service = new ExifService(new NullLogger());

        // Act
        $takenAt = $service->takenAtOf(['takenAt' => '2026-04-18 07:42:11']);

        // Assert
        static::assertSame('2026-04-18 07:42:11', $takenAt?->format('Y-m-d H:i:s'));
    }

    #[DataProvider('unusableStamps')]
    public function testTakenAtIsNullWithoutAUsableStamp(?array $meta): void
    {
        // Arrange
        $service = new ExifService(new NullLogger());

        // Act + Assert
        static::assertNull($service->takenAtOf($meta));
    }

    /** @return iterable<string, array{0: array<string, scalar>|null}> */
    public static function unusableStamps(): iterable
    {
        yield 'no meta at all' => [null];
        yield 'meta without the key' => [['make' => 'Canon']];
        yield 'meta with an unparsable value' => [['takenAt' => 'yesterday']];
    }
}
