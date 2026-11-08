<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\User;
use App\Filter\Portability\CleanImportFilterInterface;
use App\Portability\CleanImport;
use App\Portability\ExportAction;
use App\Portability\Exporter;
use App\Portability\InstanceScopeBuilder;
use App\Portability\Scope;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\MockClock;

final class CleanImportTest extends TestCase
{
    private const string NOW = '2026-11-08T12:00:00+00:00';

    #[DataProvider('provideFilterVerdicts')]
    public function testItIsOfferedOnlyWhileEveryFilterAllowsIt(array $verdicts, bool $expected): void
    {
        // Arrange
        $filters = array_map(function (bool $allows): CleanImportFilterInterface {
            $filter = $this->createStub(CleanImportFilterInterface::class);
            $filter->method('allowsCleanImport')->willReturn($allows);

            return $filter;
        }, $verdicts);

        // Act
        $isOffered = $this->cleanImport($filters)->isOffered();

        // Assert
        static::assertSame($expected, $isOffered);
    }

    public static function provideFilterVerdicts(): iterable
    {
        yield 'no filter' => [[], true];
        yield 'every filter allows it' => [[true, true], true];
        yield 'one filter refuses' => [[true, false], false];
    }

    #[DataProvider('provideBackups')]
    public function testABackupCountsOnlyWhileItIsFresh(?string $writtenAt, bool $expected): void
    {
        // Arrange
        $exportAction = $this->createStub(ExportAction::class);
        $exportAction->method('archive')->willReturn($writtenAt === null ? null : ['writtenAt' => new DateTimeImmutable($writtenAt), 'size' => 1]);

        // Act
        $hasFreshBackup = $this->cleanImport([], $exportAction)->hasFreshBackup();

        // Assert
        static::assertSame($expected, $hasFreshBackup);
    }

    public static function provideBackups(): iterable
    {
        yield 'no export at all' => [null, false];
        yield 'an export from a minute ago' => ['2026-11-08T11:59:00+00:00', true];
        yield 'an export older than the limit' => ['2026-11-08T10:59:00+00:00', false];
    }

    public function testTheCountsLeaveOutTheAdminTheCleanImportSpares(): void
    {
        // Arrange
        $admin = new User();
        new ReflectionProperty(User::class, 'id')->setValue($admin, 7);
        $scopeBuilder = $this->createStub(InstanceScopeBuilder::class);
        $scopeBuilder->method('build')->willReturn(new Scope(users: [7 => 'admin', 8 => 'user', 9 => 'user']));
        $exporter = $this->createStub(Exporter::class);
        $exporter->method('preview')->willReturn(['users' => 3, 'events' => 14]);

        // Act
        $counts = $this->cleanImport([], exporter: $exporter, scopeBuilder: $scopeBuilder)->currentCounts($admin);

        // Assert
        static::assertSame(['users' => 2, 'events' => 14], $counts);
    }

    /**
     * @param list<CleanImportFilterInterface> $filters
     */
    private function cleanImport(
        array $filters,
        ?ExportAction $exportAction = null,
        ?Exporter $exporter = null,
        ?InstanceScopeBuilder $scopeBuilder = null,
    ): CleanImport {
        return new CleanImport(
            $filters,
            $exportAction ?? $this->createStub(ExportAction::class),
            $exporter ?? $this->createStub(Exporter::class),
            $scopeBuilder ?? $this->createStub(InstanceScopeBuilder::class),
            new MockClock(self::NOW),
        );
    }
}
