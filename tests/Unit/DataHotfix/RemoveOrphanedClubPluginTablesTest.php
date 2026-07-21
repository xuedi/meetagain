<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\RemoveOrphanedClubPluginTables;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class RemoveOrphanedClubPluginTablesTest extends TestCase
{
    public function testDropsAllOrphanedTablesGuardedByForeignKeyChecks(): void
    {
        // Arrange
        $captured = [];
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$captured): int {
                $captured[] = $sql;

                return 0;
            });

        $subject = new RemoveOrphanedClubPluginTables($connection);

        // Act
        $subject->execute();

        // Assert - FK checks are toggled around a block of guarded drops
        static::assertSame('SET FOREIGN_KEY_CHECKS = 0', $captured[0]);
        static::assertSame('SET FOREIGN_KEY_CHECKS = 1', $captured[array_key_last($captured)]);

        $drops = array_slice($captured, 1, -1);
        foreach ($drops as $sql) {
            static::assertStringStartsWith('DROP TABLE IF EXISTS ', $sql);
        }
        foreach (['dish', 'dinner', 'book', 'film', 'vote_ballot', 'filmclub_group_settings'] as $table) {
            static::assertContains(sprintf('DROP TABLE IF EXISTS %s', $table), $drops);
        }
    }

    public function testIdentifierIsDatePrefixed(): void
    {
        // Arrange
        $subject = new RemoveOrphanedClubPluginTables($this->createStub(Connection::class));

        // Act
        $id = $subject->getIdentifier();

        // Assert
        static::assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_/', $id);
    }
}
