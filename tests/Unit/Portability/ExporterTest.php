<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\ExtendedFilesystem;
use App\Portability\ArchiveReader;
use App\Portability\DataCategory;
use App\Portability\DateShifter;
use App\Portability\Exporter;
use App\Portability\Item\ContributorInterface;
use App\Portability\Item\Registry;
use App\Portability\Item\UploadsInterface;
use App\Portability\PluginSectionInterface;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use App\Portability\Site;
use App\Service\Config\PluginService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use ZipArchive;

final class ExporterTest extends TestCase
{
    public function testTheArchiveCarriesTheHeaderThenTheSectionsInOrderThenTheImages(): void
    {
        // Arrange
        $scope = new Scope(eventIds: [3], plugins: ['films', 'books']);
        $seenScope = null;
        $exporter = $this->exporter([
            $this->section('events', 40, static fn(): array => ['second']),
            $this->section('locations', 10, static function (Scope $scope) use (&$seenScope): array {
                $seenScope = $scope;

                return ['first'];
            }),
        ]);

        // Act
        $data = $this->read($exporter->export($scope));

        // Assert
        static::assertSame(['format', 'version', 'exported_at', 'plugins', 'site', 'locations', 'events', 'images'], array_keys($data));
        static::assertSame(Exporter::FORMAT, $data['format']);
        static::assertSame('2.0', $data['version']);
        static::assertSame('2026-09-16T10:30:00', substr((string) $data['exported_at'], 0, 19));
        static::assertSame(['books', 'films'], $data['plugins']);
        static::assertNull($data['site']);
        static::assertSame(['first'], $data['locations']);
        static::assertSame(['second'], $data['events']);
        static::assertSame([], $data['images']);
        static::assertEquals($scope, $seenScope);
    }

    public function testTheSiteBlockCarriesTheCallersSettingsInAStableOrder(): void
    {
        // Arrange
        $site = new Site(
            slug: 'weiqi-club',
            name: 'Weiqi Club',
            description: 'Go in Berlin',
            languages: ['de', 'en'],
            themeColors: ['primary' => '#111111', 'danger' => '#222222'],
            settings: ['show_town_hall' => true, 'send_upcoming_digest' => false],
            pluginSettings: ['glossary' => ['trainerEnabled' => true], 'books' => ['circulation' => true]],
        );

        // Act
        $data = $this->read($this->exporter([])->export(new Scope(site: $site)));

        // Assert
        static::assertSame(
            [
                'slug' => 'weiqi-club',
                'name' => 'Weiqi Club',
                'description' => 'Go in Berlin',
                'languages' => ['de', 'en'],
                'theme_colors' => ['danger' => '#222222', 'primary' => '#111111'],
                'settings' => ['send_upcoming_digest' => false, 'show_town_hall' => true],
                'plugin_settings' => ['books' => ['circulation' => true], 'glossary' => ['trainerEnabled' => true]],
                'logo_file' => null,
            ],
            $data['site'],
        );
    }

    public function testAnAnchorPinsExportedAtToItsMondayAndMovesEveryDateByWholeWeeks(): void
    {
        // Arrange
        $exporter = $this->exporter([$this->section('events', 40, static fn(): array => [['start' => '2026-09-17T19:00:00+02:00']])]);

        // Act
        $data = $this->read($exporter->export(new Scope(), new DateTimeImmutable('2026-01-07')));

        // Assert
        static::assertSame('2026-01-05T00:00:00', substr((string) $data['exported_at'], 0, 19));
        static::assertSame('2026-01-08T19:00:00', substr((string) $data['events'][0]['start'], 0, 19));
    }

    public function testASectionOfAPluginTheScopeDoesNotListStaysOut(): void
    {
        // Arrange
        $pluginSection = $this->createStub(PluginSectionInterface::class);
        $pluginSection->method('getKey')->willReturn('boardgames');
        $pluginSection->method('getPluginKey')->willReturn('boardgames');
        $pluginSection->method('export')->willReturn(['ownerships' => [['game_ref' => 1]]]);

        // Act
        $data = $this->read($this->exporter([$pluginSection])->export(new Scope(plugins: ['books'])));

        // Assert
        static::assertArrayNotHasKey('boardgames', $data);
    }

    public function testUploadsOfMembersWhoDidNotGrantThemLeaveTheScopeBeforeAnySectionReadsIt(): void
    {
        // Arrange
        $photos = $this->createStubForIntersectionOfInterfaces([ContributorInterface::class, UploadsInterface::class]);
        $photos->method('getItemType')->willReturn('photo');
        $photos->method('getUploaderIds')->willReturn([10 => 1, 11 => 2, 12 => null]);
        $seenScope = null;
        $section = $this->section('comments', 80, static function (Scope $scope) use (&$seenScope): array {
            $seenScope = $scope;

            return [];
        });
        $scope = new Scope(
            users: [1 => 'user', 2 => 'user'],
            itemIds: ['photo' => [10, 11, 12], 'book' => [4]],
            grants: [1 => [DataCategory::Uploads], 2 => [DataCategory::Interactions]],
        );

        // Act
        $this->read($this->exporter([$section], [$photos])->export($scope));

        // Assert
        static::assertSame(['photo' => [10], 'book' => [4]], $seenScope?->itemIds);
    }

    public function testAWholeInstanceScopeCarriesEveryUploadEvenWithoutAMemberBehindIt(): void
    {
        // Arrange
        $photos = $this->createStubForIntersectionOfInterfaces([ContributorInterface::class, UploadsInterface::class]);
        $photos->method('getItemType')->willReturn('photo');
        $photos->method('getUploaderIds')->willReturn([10 => 1, 11 => 99, 12 => null]);
        $seenScope = null;
        $section = $this->section('comments', 80, static function (Scope $scope) use (&$seenScope): array {
            $seenScope = $scope;

            return [];
        });
        $scope = new Scope(users: [1 => 'user'], itemIds: ['photo' => [10, 11, 12]], grants: [1 => []], everyUpload: true);

        // Act
        $this->read($this->exporter([$section], [$photos])->export($scope));

        // Assert
        static::assertSame(['photo' => [10, 11, 12]], $seenScope?->itemIds);
        static::assertTrue($seenScope?->everyUpload);
    }

    public function testThePreviewCountsTheRowsPerKindWithoutWritingAnArchive(): void
    {
        // Arrange
        $exporter = $this->exporter([
            $this->section('events', 40, static fn(): array => [['ref' => 1], ['ref' => 2]]),
            $this->section('circulation', 75, static fn(): array => ['copies' => [['id' => 7]], 'requests' => []]),
            $this->section('rsvps', 45, static fn(): array => []),
        ]);

        // Act
        $counts = $exporter->preview(new Scope());

        // Assert
        static::assertSame(['events' => 2, 'circulation_copies' => 1], $counts);
    }

    /**
     * @param list<SectionInterface> $sections
     * @param list<ContributorInterface> $contributors
     */
    private function exporter(array $sections, array $contributors = []): Exporter
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('all')->willReturn($contributors);

        $archiveReader = new ArchiveReader($this->createStub(ExtendedFilesystem::class), $this->createStub(PluginService::class));

        return new Exporter($sections, $registry, $archiveReader, new DateShifter(), new MockClock('2026-09-16 10:30:00'), '/nonexistent');
    }

    /**
     * @param callable(Scope): array<array-key, mixed> $export
     */
    private function section(string $key, int $order, callable $export): SectionInterface
    {
        $section = $this->createStub(SectionInterface::class);
        $section->method('getKey')->willReturn($key);
        $section->method('getOrder')->willReturn($order);
        $section->method('export')->willReturnCallback($export);

        return $section;
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $zipPath): array
    {
        $zip = new ZipArchive();
        $zip->open($zipPath);
        $data = json_decode((string) $zip->getFromName('export.json'), true, 512, JSON_THROW_ON_ERROR);
        $zip->close();
        unlink($zipPath);

        return $data;
    }
}
