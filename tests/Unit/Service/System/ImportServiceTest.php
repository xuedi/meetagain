<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\EventInterval;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use App\Repository\LocationRepository;
use App\Repository\UserRepository;
use App\Service\System\ImportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ImportServiceTest extends TestCase
{
    private const string PROJECT_DIR = '/app';

    public function testImportImageReturnsNullWhenSourcePathDoesNotExist(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(false);
        $service = $this->buildService($fs);

        // Act
        $result = $this->invokeImportImage($service, '/missing.png');

        // Assert
        static::assertNull($result);
    }

    public function testImportImageReturnsExistingImageWhenHashAlreadyPersisted(): void
    {
        // Arrange
        $existing = new Image();
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('fake-image-bytes');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn($existing);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);

        $service = $this->buildService($fs, $em);

        // Act
        $result = $this->invokeImportImage($service, '/source.png');

        // Assert
        static::assertSame($existing, $result);
    }

    public function testImportImageReturnsNullWhenExtensionIsEmpty(): void
    {
        // Arrange - file exists, hash not known, but path has no extension
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('bytes');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);

        $service = $this->buildService($fs, $em);

        // Act - path "/source-no-extension" → pathinfo extension is empty
        $result = $this->invokeImportImage($service, '/source-no-extension');

        // Assert
        static::assertNull($result);
    }

    public function testImportImagePersistsAndWritesNewImage(): void
    {
        // Arrange
        $bytes = 'png-bytes';
        $expectedHash = sha1($bytes);
        $writtenPath = null;
        $persisted = null;

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn($bytes);
        $fs->method('isDirectory')->willReturn(true);
        $fs->method('putFileContents')->willReturnCallback(static function (string $path) use (&$writtenPath): bool {
            $writtenPath = $path;
            return true;
        });

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            if ($entity instanceof Image) {
                $persisted = $entity;
            }
        });

        $service = $this->buildService($fs, $em);

        // Act
        $result = $this->invokeImportImage($service, '/source.png');

        // Assert
        static::assertNotNull($result);
        static::assertSame($expectedHash, $result->getHash());
        static::assertSame('png', $result->getExtension());
        static::assertSame(strlen($bytes), $result->getSize());
        static::assertSame($result, $persisted);
        static::assertSame(self::PROJECT_DIR . '/data/images/' . $expectedHash . '.png', $writtenPath);
    }

    public function testRemoveDirectoryIsNoOpWhenPathIsNotADirectory(): void
    {
        // Arrange
        $deleteCalls = 0;
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isDirectory')->willReturn(false);
        $fs->method('deleteFile')->willReturnCallback(static function () use (&$deleteCalls): bool {
            $deleteCalls++;
            return true;
        });

        $service = $this->buildService($fs);

        // Act
        $this->invokeRemoveDirectory($service, '/not-a-dir');

        // Assert
        static::assertSame(0, $deleteCalls);
    }

    public function testRemoveDirectoryRecursesAndDeletesEntries(): void
    {
        // Arrange - layout: /root/{.,..,a.txt,sub/{.,..,b.txt}}
        $deletedFiles = [];
        $removedDirs = [];

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isDirectory')->willReturnCallback(static fn(string $path): bool => in_array($path, ['/root', '/root/sub'], true));
        $fs->method('scanDirectory')->willReturnCallback(static fn(string $path): array => match ($path) {
            '/root' => ['.', '..', 'a.txt', 'sub'],
            '/root/sub' => ['.', '..', 'b.txt'],
            default => [],
        });
        $fs->method('deleteFile')->willReturnCallback(static function (string $path) use (&$deletedFiles): bool {
            $deletedFiles[] = $path;
            return true;
        });
        $fs->method('removeDirectory')->willReturnCallback(static function (string $path) use (&$removedDirs): bool {
            $removedDirs[] = $path;
            return true;
        });

        $service = $this->buildService($fs);

        // Act
        $this->invokeRemoveDirectory($service, '/root');

        // Assert
        static::assertSame(['/root/a.txt', '/root/sub/b.txt'], $deletedFiles);
        // Subdir removed before root
        static::assertSame(['/root/sub', '/root'], $removedDirs);
    }

    public function testImportEventsSynthesizesSeriesFromLegacyRecurringRule(): void
    {
        // Arrange
        [$service, $persisted] = $this->buildCapturingService();

        $eventsData = [
            [
                'start' => '2030-01-01 10:00',
                'titles' => ['en' => 'My Legacy Event', 'de' => 'Mein Alt-Event'],
                'recurring_rule' => 'Weekly',
            ],
        ];

        // Act
        $this->invokeImportEvents($service, $eventsData, []);

        // Assert: exactly one series synthesized, named after the en title
        $series = array_values(array_filter($persisted(), static fn(object $entity): bool => $entity instanceof EventSeries));
        static::assertCount(1, $series);
        static::assertSame('My Legacy Event', $series[0]->getName());
        static::assertSame(EventInterval::Weekly, $series[0]->getRule());

        $events = array_values(array_filter($persisted(), static fn(object $entity): bool => $entity instanceof Event));
        static::assertCount(1, $events);
        static::assertSame($series[0], $events[0]->getSeries());
    }

    public function testImportEventsAttachesSeriesByRef(): void
    {
        // Arrange
        [$service, $persisted] = $this->buildCapturingService();
        $seriesRefMap = $this->invokeImportSeries($service, [
            ['ref' => 1, 'name' => 'Series A', 'rule' => 'Monthly'],
            ['ref' => 2, 'name' => 'Series B', 'rule' => null],
        ]);

        $eventsData = [
            [
                'start' => '2030-01-01 10:00',
                'titles' => ['en' => 'Member Event'],
                'series_ref' => 2,
            ],
        ];

        // Act
        $this->invokeImportEvents($service, $eventsData, $seriesRefMap);

        // Assert: member attached by ref, closed series keeps a null rule
        static::assertSame(EventInterval::Monthly, $seriesRefMap[1]->getRule());
        static::assertNull($seriesRefMap[2]->getRule());
        static::assertSame('Series B', $seriesRefMap[2]->getName());

        $events = array_values(array_filter($persisted(), static fn(object $entity): bool => $entity instanceof Event));
        static::assertCount(1, $events);
        static::assertSame($seriesRefMap[2], $events[0]->getSeries());
    }

    public function testImportEventsWithoutSeriesDataStaysSeriesless(): void
    {
        // Arrange
        [$service, $persisted] = $this->buildCapturingService();

        $eventsData = [
            [
                'start' => '2030-01-01 10:00',
                'titles' => ['en' => 'One-Time Event'],
            ],
        ];

        // Act
        $this->invokeImportEvents($service, $eventsData, []);

        // Assert
        static::assertCount(0, array_filter($persisted(), static fn(object $entity): bool => $entity instanceof EventSeries));
        $events = array_values(array_filter($persisted(), static fn(object $entity): bool => $entity instanceof Event));
        static::assertCount(1, $events);
        static::assertNull($events[0]->getSeries());
    }

    /**
     * @return array{ImportService, callable(): array<object>}
     */
    private function buildCapturingService(): array
    {
        $persisted = [];

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $service = $this->buildService($this->createStub(ExtendedFilesystem::class), $em);

        return [
            $service,
            static function () use (&$persisted): array {
                return $persisted;
            },
        ];
    }

    /**
     * @param array<array<string, mixed>> $seriesData
     * @return array<int, EventSeries>
     */
    private function invokeImportSeries(ImportService $service, array $seriesData): array
    {
        $method = new ReflectionMethod($service, 'importSeries');

        return $method->invoke($service, $seriesData);
    }

    /**
     * @param array<array<string, mixed>> $eventsData
     * @param array<int, EventSeries> $seriesRefMap
     */
    private function invokeImportEvents(ImportService $service, array $eventsData, array $seriesRefMap): void
    {
        $counts = ['eventsCreated' => 0];
        $method = new ReflectionMethod($service, 'importEvents');
        $args = [$eventsData, [], $seriesRefMap, [], new User(), '/tmp', &$counts];
        $method->invokeArgs($service, $args);
    }

    private function buildService(ExtendedFilesystem $fs, ?EntityManagerInterface $em = null): ImportService
    {
        return new ImportService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            userRepository: $this->createStub(UserRepository::class),
            locationRepository: $this->createStub(LocationRepository::class),
            fs: $fs,
            projectDir: self::PROJECT_DIR,
        );
    }

    private function invokeImportImage(ImportService $service, string $path): ?Image
    {
        $method = new ReflectionMethod($service, 'importImage');
        return $method->invoke($service, $path, ImageType::EventUpload, new User());
    }

    private function invokeRemoveDirectory(ImportService $service, string $path): void
    {
        $method = new ReflectionMethod($service, 'removeDirectory');
        $method->invoke($service, $path);
    }
}
