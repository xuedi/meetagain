<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use App\Service\AppStateService;
use Doctrine\DBAL\Connection;
use Override;
use RuntimeException;

readonly class DropItemCategoryAssignment implements DataHotfixInterface
{
    public const string ARMED_KEY = 'item_tag_migration.drop_armed';

    private const string CONVERSION_LOCK = 'data_hotfix.2026_08_01_item_taxonomy_to_tags';
    private const string FOREIGN_KEY = 'FK_item_tag_assignment_tag';

    public function __construct(
        private Connection $connection,
        private AppStateService $appState,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_08_02_drop_item_category_assignment';
    }

    #[Override]
    public function execute(): void
    {
        if ($this->appState->get(self::CONVERSION_LOCK) === null) {
            throw new RuntimeException('Deferring until ItemTaxonomyToTags has completed.');
        }

        if ($this->appState->get(self::ARMED_KEY) === null) {
            $this->appState->set(self::ARMED_KEY, 'armed');

            throw new RuntimeException('Deferring one tick so every re-keying hotfix has had its turn.');
        }

        $this->connection->executeStatement('DELETE a FROM item_tag_assignment a LEFT JOIN item_tag t ON t.id = a.tag_id WHERE t.id IS NULL');
        $this->connection->executeStatement('DROP TABLE IF EXISTS item_category_assignment');

        if ($this->hasForeignKey()) {
            return;
        }

        $this->connection->executeStatement(sprintf(
            'ALTER TABLE item_tag_assignment ADD CONSTRAINT %s FOREIGN KEY (tag_id) REFERENCES item_tag (id) ON DELETE CASCADE',
            self::FOREIGN_KEY,
        ));
    }

    private function hasForeignKey(): bool
    {
        return $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME = ?',
            ['item_tag_assignment', 'item_tag'],
        ) > 0;
    }
}
