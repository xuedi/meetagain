<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\User;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use App\Portability\ArchiveReader;
use App\Portability\DateShifter;
use App\Portability\Exporter;
use App\Portability\ImageImporter;
use App\Portability\Importer;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\PluginSectionInterface;
use App\Portability\SectionInterface;
use App\Portability\SiteSettings;
use App\Repository\UserRepository;
use App\Service\Config\PluginService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use ZipArchive;

final class ImporterTest extends TestCase
{
    /** @var list<array{string, array<array-key, mixed>}> */
    private array $calls = [];

    private string $zipPath = '';

    private string $directory = '';

    protected function tearDown(): void
    {
        if ($this->zipPath !== '' && file_exists($this->zipPath)) {
            unlink($this->zipPath);
        }

        if ($this->directory !== '' && is_dir($this->directory)) {
            unlink($this->directory . '/export.json');
            rmdir($this->directory);
        }
    }

    public function testAnUnpackedArchiveImportsFromItsDirectoryAndIsLeftInPlace(): void
    {
        // Arrange
        $this->directory = sys_get_temp_dir() . '/importer-test-' . uniqid('', true);
        mkdir($this->directory);
        file_put_contents($this->directory . '/export.json', json_encode(['format' => Exporter::FORMAT, 'locations' => [['ref' => 1]]], JSON_THROW_ON_ERROR));
        $importer = $this->importer([$this->section('locations', 10)]);

        // Act
        $importer->import($this->directory);

        // Assert
        static::assertSame([['locations', [['ref' => 1]]]], $this->calls);
        static::assertFileExists($this->directory . '/export.json');
    }

    public function testSectionsImportInAscendingOrderEachWithItsOwnBlock(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT, 'events' => [['start' => 'x']], 'locations' => [['ref' => 1]]]);
        $importer = $this->importer([$this->section('events', 40), $this->section('locations', 10)]);

        // Act
        $importer->import($this->zipPath);

        // Assert
        static::assertSame([['locations', [['ref' => 1]]], ['events', [['start' => 'x']]]], $this->calls);
    }

    public function testASectionTheArchiveLacksReceivesNoRows(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT]);
        $importer = $this->importer([$this->section('users', 20)]);

        // Act
        $importer->import($this->zipPath);

        // Assert
        static::assertSame([['users', []]], $this->calls);
    }

    public function testAVersionOneArchiveStillImportsItsSections(): void
    {
        // Arrange
        $this->zipPath = $this->archive([
            'version' => '1.2',
            'format' => Exporter::FORMAT,
            'source_group' => ['name' => 'Weiqi Club'],
            'users' => [['email' => 'ada@example.org', 'role' => 'organizer']],
        ]);
        $importer = $this->importer([$this->section('users', 20)]);

        // Act
        $summary = $importer->import($this->zipPath, applySite: true);

        // Assert
        static::assertSame([['users', [['email' => 'ada@example.org', 'role' => 'organizer']]]], $this->calls);
        static::assertFalse($summary->siteApplied);
    }

    public function testAnArchiveOfAnotherFormatIsRejected(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => 'something-else']);
        $importer = $this->importer([$this->section('users', 20)]);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid export format');

        // Act
        $importer->import($this->zipPath);
    }

    public function testAnInstanceWithoutTheImportUserIsRejected(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT]);
        $importer = $this->importer([$this->section('users', 20)], systemUser: null);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('app:install:seed');

        // Act
        $importer->import($this->zipPath);
    }

    public function testShiftingMovesEveryDateToTheCurrentWeekKeepingWeekdayAndTime(): void
    {
        // Arrange
        $this->zipPath = $this->archive([
            'format' => Exporter::FORMAT,
            'exported_at' => '2026-01-05T00:00:00+01:00',
            'events' => [['start' => '2026-01-07T19:00:00+01:00']],
        ]);
        $importer = $this->importer([$this->section('events', 40)]);

        // Act
        $summary = $importer->import($this->zipPath, shiftDates: true);

        // Assert
        static::assertSame(36, $summary->weeksShifted);
        static::assertSame('2026-09-16T19:00:00', substr((string) $this->calls[0][1][0]['start'], 0, 19));
    }

    public function testWithoutShiftingTheDatesStayAsExported(): void
    {
        // Arrange
        $this->zipPath = $this->archive([
            'format' => Exporter::FORMAT,
            'exported_at' => '2026-01-05T00:00:00+01:00',
            'events' => [['start' => '2026-01-07T19:00:00+01:00']],
        ]);
        $importer = $this->importer([$this->section('events', 40)]);

        // Act
        $summary = $importer->import($this->zipPath);

        // Assert
        static::assertSame(0, $summary->weeksShifted);
        static::assertSame('2026-01-07T19:00:00+01:00', $this->calls[0][1][0]['start']);
    }

    public function testShiftingAnArchiveWithoutExportDateIsRejected(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT]);
        $importer = $this->importer([]);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exported_at');

        // Act
        $importer->import($this->zipPath, shiftDates: true);
    }

    public function testTheSiteBlockIsWrittenWhenAsked(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT, 'site' => ['name' => 'Weiqi Club']]);
        $siteSettings = $this->createMock(SiteSettings::class);
        $siteSettings->expects($this->once())->method('apply')->with(['name' => 'Weiqi Club']);

        // Act
        $summary = $this->importer([], siteSettings: $siteSettings)->import($this->zipPath, applySite: true);

        // Assert
        static::assertTrue($summary->siteApplied);
    }

    public function testTheSiteBlockIsLeftAloneByDefault(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT, 'site' => ['name' => 'Weiqi Club']]);
        $siteSettings = $this->createMock(SiteSettings::class);
        $siteSettings->expects($this->never())->method('apply');

        // Act
        $summary = $this->importer([], siteSettings: $siteSettings)->import($this->zipPath);

        // Assert
        static::assertFalse($summary->siteApplied);
    }

    public function testPluginsThisInstanceLacksAreReported(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT, 'plugins' => ['books', 'films']]);

        // Act
        $summary = $this->importer([], activePlugins: ['books'])->import($this->zipPath);

        // Assert
        static::assertSame(['films'], $summary->missingPlugins);
    }

    public function testASectionOfAnInactivePluginIsNotRunAndItsRowsCountAsSkipped(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT, 'boardgames' => ['ownerships' => [['game_ref' => 1], ['game_ref' => 2]]]]);
        $section = $this->createMock(PluginSectionInterface::class);
        $section->method('getKey')->willReturn('boardgames');
        $section->method('getPluginKey')->willReturn('boardgames');
        $section->expects($this->never())->method('import');

        // Act
        $summary = $this->importer([$section], activePlugins: ['books'])->import($this->zipPath);

        // Assert
        static::assertSame(2, $summary->get('boardgames_ownerships', Outcome::Skipped));
    }

    public function testABlockNoSectionClaimsCountsAsSkipped(): void
    {
        // Arrange
        $this->zipPath = $this->archive(['format' => Exporter::FORMAT, 'wishlist' => [['item_ref' => 1]], 'users' => [['email' => 'ada@example.org']]]);

        // Act
        $summary = $this->importer([$this->section('users', 20)])->import($this->zipPath);

        // Assert
        static::assertSame(1, $summary->get('wishlist', Outcome::Skipped));
        static::assertSame(0, $summary->get('users', Outcome::Skipped));
    }

    public function testImageAttributionsTravelToTheImageImporter(): void
    {
        // Arrange
        $this->zipPath = $this->archive([
            'format' => Exporter::FORMAT,
            'events' => [['image_file' => 'images/a.jpg']],
            'images' => ['images/a.jpg' => ['attribution' => 'Photo: Ada, CC BY 4.0', 'attribution_not_required' => false]],
        ]);
        $imageImporter = $this->createMock(ImageImporter::class);
        $imageImporter
            ->expects($this->once())
            ->method('import')
            ->with(static::stringEndsWith('/images/a.jpg'), ImageType::EventTeaser, static::isInstanceOf(User::class), 'Photo: Ada, CC BY 4.0', false);

        $section = $this->createStub(SectionInterface::class);
        $section->method('getKey')->willReturn('events');
        $section->method('import')->willReturnCallback(static function (array $rows, ImportContext $context): void {
            $context->importImage($rows[0]['image_file'], ImageType::EventTeaser);
        });

        // Act
        $this->importer([$section], imageImporter: $imageImporter)->import($this->zipPath);
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

        // Act
        new ReflectionMethod(Importer::class, 'removeDirectory')->invoke($this->importer([], $fs), '/not-a-dir');

        // Assert
        static::assertSame(0, $deleteCalls);
    }

    public function testRemoveDirectoryRecursesAndDeletesEntries(): void
    {
        // Arrange
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

        // Act
        new ReflectionMethod(Importer::class, 'removeDirectory')->invoke($this->importer([], $fs), '/root');

        // Assert
        static::assertSame(['/root/a.txt', '/root/sub/b.txt'], $deletedFiles);
        static::assertSame(['/root/sub', '/root'], $removedDirs);
    }

    /**
     * @param list<SectionInterface> $sections
     * @param list<string> $activePlugins
     */
    private function importer(
        array $sections,
        ?ExtendedFilesystem $fs = null,
        ?User $systemUser = new User(),
        ?SiteSettings $siteSettings = null,
        ?ImageImporter $imageImporter = null,
        array $activePlugins = [],
    ): Importer {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn(callable $work): mixed => $work());

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneBy')->willReturn($systemUser);

        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($activePlugins);

        $fs ??= $this->localFilesystem();

        return new Importer(
            $sections,
            $em,
            $userRepository,
            $fs,
            $imageImporter ?? $this->createStub(ImageImporter::class),
            new ArchiveReader($fs, $pluginService),
            $siteSettings ?? $this->createStub(SiteSettings::class),
            new DateShifter(),
            new MockClock('2026-09-16 12:00:00'),
            $pluginService,
        );
    }

    private function section(string $key, int $order): SectionInterface
    {
        $section = $this->createStub(SectionInterface::class);
        $section->method('getKey')->willReturn($key);
        $section->method('getOrder')->willReturn($order);
        $section->method('import')->willReturnCallback(function (array $rows) use ($key): void {
            $this->calls[] = [$key, $rows];
        });

        return $section;
    }

    private function localFilesystem(): ExtendedFilesystem
    {
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('makeDirectory')->willReturnCallback(static fn(string $path): bool => mkdir($path, 0o777, true));
        $fs->method('fileExists')->willReturnCallback(file_exists(...));
        $fs->method('getFileContents')->willReturnCallback(file_get_contents(...));
        $fs->method('isDirectory')->willReturnCallback(is_dir(...));
        $fs->method('scanDirectory')->willReturnCallback(static fn(string $path): array => scandir($path) ?: []);
        $fs->method('deleteFile')->willReturnCallback(unlink(...));
        $fs->method('removeDirectory')->willReturnCallback(rmdir(...));

        return $fs;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function archive(array $data): string
    {
        $path = sys_get_temp_dir() . '/importer-test-' . uniqid('', true) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('export.json', json_encode($data, JSON_THROW_ON_ERROR));
        $zip->close();

        return $path;
    }
}
