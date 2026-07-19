<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\RemoveOrphanedClubPluginMigrations;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class RemoveOrphanedClubPluginMigrationsTest extends TestCase
{
    public function testDeletesRowsForEachRemovedClubNamespace(): void
    {
        // Arrange
        $captured = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('1');
        $connection->method('executeStatement')->willReturnCallback(
            function (string $sql, array $params = []) use (&$captured): int {
                $captured[] = ['sql' => $sql, 'params' => $params];

                return 1;
            },
        );

        $subject = new RemoveOrphanedClubPluginMigrations($connection);

        // Act
        $subject->execute();

        // Assert
        static::assertCount(3, $captured);
        foreach ($captured as $call) {
            static::assertStringContainsString('DELETE FROM doctrine_migration_versions', $call['sql']);
            static::assertStringContainsString('WHERE version LIKE ?', $call['sql']);
        }
        static::assertSame(['PluginBookclubMigrations%'], $captured[0]['params']);
        static::assertSame(['PluginDinnerclubMigrations%'], $captured[1]['params']);
        static::assertSame(['PluginFilmclubMigrations%'], $captured[2]['params']);
    }

    public function testNoOpsWhenMigrationsTableIsAbsent(): void
    {
        // Arrange
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('0');
        $connection->expects($this->never())->method('executeStatement');

        $subject = new RemoveOrphanedClubPluginMigrations($connection);

        // Act
        $subject->execute();

        // Assert - expectation on executeStatement asserts it was never called
    }

    public function testIdentifierIsDatePrefixed(): void
    {
        // Arrange
        $subject = new RemoveOrphanedClubPluginMigrations($this->createStub(Connection::class));

        // Act
        $id = $subject->getIdentifier();

        // Assert
        static::assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_/', $id);
    }
}
