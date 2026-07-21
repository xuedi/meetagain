<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\DataHotfix;

use App\Service\Config\LanguageService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\DataHotfix\Hotfixes\MigrateGlossaryCategories;

class MigrateGlossaryCategoriesTest extends TestCase
{
    public function testBackfillsAssignmentsFromLegacyColumn(): void
    {
        // Arrange: the legacy category column is present, one entry has category 5, not yet assigned
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(
            static fn(string $sql): mixed => str_contains($sql, 'information_schema') ? 1 : false,
        );
        $connection->method('fetchAllAssociative')->willReturn([['id' => 1, 'category' => 5]]);
        $connection->method('fetchAssociative')->willReturn(false); // no global config row
        $connection->expects(self::once())->method('insert')->with('item_category_assignment', [
            'item_type' => 'glossary',
            'item_id' => 1,
            'category_id' => 5,
        ]);

        // Act
        $this->makeSubject($connection)->execute();

        // Assert - insert asserted via mock
    }

    public function testSkipsBackfillWhenLegacyColumnAbsentButRewritesConfig(): void
    {
        // Arrange: no category column (dev/test schema), config still in the old single-label shape
        $oldConfig = json_encode(['secondaryEnabled' => true, 'categories' => [['id' => 0, 'label' => 'Greeting']]]);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(0); // information_schema: column absent
        $connection->expects(self::never())->method('fetchAllAssociative');
        $connection->method('fetchAssociative')->willReturn(['id' => 9, 'data' => $oldConfig]);
        $connection->expects(self::once())->method('update')->willReturnCallback(
            function (string $table, array $set, array $where): int {
                self::assertSame('plugin_settings', $table);
                self::assertSame(['id' => 9], $where);
                $decoded = json_decode((string) $set['data'], true);
                self::assertArrayNotHasKey('categories', $decoded);
                self::assertTrue($decoded['taxonomy']['categoriesEnabled']);
                self::assertSame(['en' => 'Greeting'], $decoded['taxonomy']['categories'][0]['labels']);

                return 1;
            },
        );

        // Act
        $this->makeSubject($connection)->execute();

        // Assert - update asserted via callback
    }

    public function testIdempotentConfigRewriteWhenAlreadyMigrated(): void
    {
        // Arrange: config already carries a taxonomy key
        $newConfig = json_encode(['taxonomy' => ['categoriesEnabled' => true, 'categories' => [], 'tags' => []]]);
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(0);
        $connection->method('fetchAssociative')->willReturn(['id' => 9, 'data' => $newConfig]);
        $connection->expects(self::never())->method('update');

        // Act
        $this->makeSubject($connection)->execute();

        // Assert - update never called (no-op)
    }

    private function makeSubject(Connection $connection): MigrateGlossaryCategories
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredDefaultLocale')->willReturn('en');

        return new MigrateGlossaryCategories($em, $languageService);
    }
}
