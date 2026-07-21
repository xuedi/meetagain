<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\Entity\Event;
use App\Entity\EventSeries;
use App\Entity\User;
use App\Enum\EventInterval;
use App\ExtendedFilesystem;
use App\Item\Portability\ItemImportResult;
use App\Item\Portability\ItemPortabilityContributorInterface;
use App\Item\Portability\ItemPortabilityRegistry;
use App\Item\Portability\ItemTaxonomyPortability;
use App\Repository\LocationRepository;
use App\Repository\UserRepository;
use App\Service\System\ImportService;
use App\Service\System\PortableImageImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ImportServiceTest extends TestCase
{
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

    public function testItemStageCollectsPerTypeCountsAndRekeysTaxonomy(): void
    {
        // Arrange
        $contributor = $this->stubContributor('dish', new ItemImportResult([7 => 91], created: 1, matched: 2));
        $registry = $this->createStub(ItemPortabilityRegistry::class);
        $registry->method('contributorFor')->willReturnCallback(static fn(string $type): ?ItemPortabilityContributorInterface => $type === 'dish'
            ? $contributor
            : null);

        $seenMap = null;
        $taxonomy = $this->createStub(ItemTaxonomyPortability::class);
        $taxonomy
            ->method('import')
            ->willReturnCallback(static function (string $type, array $block, array $map) use (&$seenMap): int {
                $seenMap = $map;
                return 3;
            });

        $service = $this->buildService($this->createStub(ExtendedFilesystem::class), null, $registry, $taxonomy);
        $counts = ['itemSectionsSkipped' => 0, 'taxonomyAssignmentsDropped' => 0];

        // Act
        $byType = $this->invokeImportItems($service, ['dish' => ['rows' => [['ref' => 7]]]], $counts);

        // Assert
        static::assertSame(['dish' => ['created' => 1, 'matched' => 2]], $byType);
        static::assertSame([7 => 91], $seenMap);
        static::assertSame(3, $counts['taxonomyAssignmentsDropped']);
        static::assertSame(0, $counts['itemSectionsSkipped']);
    }

    public function testItemStageSkipsAndCountsSectionsOfUnknownTypes(): void
    {
        // Arrange
        $registry = $this->createStub(ItemPortabilityRegistry::class);
        $registry->method('contributorFor')->willReturn(null);
        $service = $this->buildService($this->createStub(ExtendedFilesystem::class), null, $registry);
        $counts = ['itemSectionsSkipped' => 0, 'taxonomyAssignmentsDropped' => 0];

        // Act
        $byType = $this->invokeImportItems($service, ['karaoke' => ['rows' => [['ref' => 1]]]], $counts);

        // Assert
        static::assertSame([], $byType);
        static::assertSame(1, $counts['itemSectionsSkipped']);
    }

    public function testItemStageIsANoOpForExportsWithoutAnItemsSection(): void
    {
        // Arrange
        $registry = $this->createStub(ItemPortabilityRegistry::class);
        $registry->method('contributorFor')->willReturn($this->stubContributor('dish', new ItemImportResult([], 0, 0)));
        $service = $this->buildService($this->createStub(ExtendedFilesystem::class), null, $registry);
        $counts = ['itemSectionsSkipped' => 0, 'taxonomyAssignmentsDropped' => 0];

        // Act
        $byType = $this->invokeImportItems($service, [], $counts);

        // Assert
        static::assertSame([], $byType);
        static::assertSame(0, $counts['itemSectionsSkipped']);
        static::assertSame(0, $counts['taxonomyAssignmentsDropped']);
    }

    private function stubContributor(string $itemType, ItemImportResult $result): ItemPortabilityContributorInterface
    {
        $contributor = $this->createStub(ItemPortabilityContributorInterface::class);
        $contributor->method('getItemType')->willReturn($itemType);
        $contributor->method('importItems')->willReturn($result);

        return $contributor;
    }

    /**
     * @param array<string, mixed> $itemsData
     * @param array<string, int> $counts
     * @return array<string, array{created: int, matched: int}>
     */
    private function invokeImportItems(ImportService $service, array $itemsData, array &$counts): array
    {
        $method = new ReflectionMethod($service, 'importItems');
        $args = [$itemsData, '/tmp', new User(), &$counts];

        return $method->invokeArgs($service, $args);
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

    private function buildService(
        ExtendedFilesystem $fs,
        ?EntityManagerInterface $em = null,
        ?ItemPortabilityRegistry $registry = null,
        ?ItemTaxonomyPortability $taxonomy = null,
    ): ImportService {
        return new ImportService(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            userRepository: $this->createStub(UserRepository::class),
            locationRepository: $this->createStub(LocationRepository::class),
            fs: $fs,
            imageImporter: $this->createStub(PortableImageImporter::class),
            itemRegistry: $registry ?? $this->createStub(ItemPortabilityRegistry::class),
            taxonomyPortability: $taxonomy ?? $this->createStub(ItemTaxonomyPortability::class),
        );
    }

    private function invokeRemoveDirectory(ImportService $service, string $path): void
    {
        $method = new ReflectionMethod($service, 'removeDirectory');
        $method->invoke($service, $path);
    }
}
