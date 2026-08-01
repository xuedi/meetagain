<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix;

use App\DataHotfix\Hotfixes\DropItemCategoryAssignment;
use App\Service\AppStateService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DropItemCategoryAssignmentTest extends TestCase
{
    private const string CONVERSION_LOCK = 'data_hotfix.2026_08_01_item_taxonomy_to_tags';

    public function testDefersWhileTheConversionHasNotRun(): void
    {
        // Arrange
        $statements = [];
        $state = [];
        $subject = new DropItemCategoryAssignment($this->connection($statements), $this->appState($state));

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $subject->execute();
    }

    public function testTheFirstRunAfterTheConversionOnlyArms(): void
    {
        // Arrange
        $statements = [];
        $state = [self::CONVERSION_LOCK => 'done'];
        $subject = new DropItemCategoryAssignment($this->connection($statements), $this->appState($state));

        // Act
        $error = null;
        try {
            $subject->execute();
        } catch (RuntimeException $exception) {
            $error = $exception;
        }

        // Assert
        self::assertNotNull($error);
        self::assertSame([], $statements);
        self::assertArrayHasKey(DropItemCategoryAssignment::ARMED_KEY, $state);
    }

    public function testTheArmedRunDropsTheTableAndAddsTheForeignKey(): void
    {
        // Arrange
        $statements = [];
        $state = [self::CONVERSION_LOCK => 'done', DropItemCategoryAssignment::ARMED_KEY => 'armed'];
        $subject = new DropItemCategoryAssignment($this->connection($statements), $this->appState($state));

        // Act
        $subject->execute();

        // Assert
        self::assertStringContainsString('DELETE a FROM item_tag_assignment', $statements[0]);
        self::assertSame('DROP TABLE IF EXISTS item_category_assignment', $statements[1]);
        self::assertStringContainsString('ADD CONSTRAINT', $statements[2]);
    }

    /** @param list<string> $statements */
    private function connection(array &$statements): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 0;
        });
        $connection->method('fetchOne')->willReturn(0);

        return $connection;
    }

    /** @param array<string, string> $state */
    private function appState(array &$state): AppStateService
    {
        $appState = $this->createStub(AppStateService::class);
        $appState->method('get')->willReturnCallback(static fn(string $key): ?string => $state[$key] ?? null);
        $appState->method('set')->willReturnCallback(static function (string $key, string $value) use (&$state): void {
            $state[$key] = $value;
        });

        return $appState;
    }
}
