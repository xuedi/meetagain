<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008190000 extends AbstractMigration
{
    private const string CONVERSION_LOCK = 'data_hotfix.2026_08_01_item_taxonomy_to_tags';

    public function getDescription(): string
    {
        return 'item_tag_assignment gets its tag foreign key and item_category_assignment is dropped once empty or converted - both were left to a data hotfix that only runs on a cron tick';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE a FROM item_tag_assignment a LEFT JOIN item_tag t ON t.id = a.tag_id WHERE t.id IS NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS fk_item_tag_assignment_tag ON item_tag_assignment (tag_id)');
        if (!$this->hasTagForeignKey()) {
            $this->addSql('ALTER TABLE item_tag_assignment ADD CONSTRAINT FK_8433611FBAD26311 FOREIGN KEY (tag_id) REFERENCES item_tag (id) ON DELETE CASCADE');
        }

        if ($this->categoryAssignmentsAreDisposable()) {
            $this->addSql('DROP TABLE IF EXISTS item_category_assignment');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_tag_assignment DROP FOREIGN KEY IF EXISTS FK_8433611FBAD26311');
        $this->addSql(<<<'SQL'
                CREATE TABLE IF NOT EXISTS item_category_assignment (
                    id INT AUTO_INCREMENT NOT NULL,
                    item_type VARCHAR(50) NOT NULL,
                    item_id INT NOT NULL,
                    category_id INT NOT NULL,
                    UNIQUE INDEX uniq_item_category (item_type, item_id),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    private function hasTagForeignKey(): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME = ?', [
            'item_tag_assignment',
            'item_tag',
        ]) > 0;
    }

    private function categoryAssignmentsAreDisposable(): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['item_category_assignment'])) {
            return false;
        }

        $converted = $this->connection->fetchOne('SELECT 1 FROM app_state WHERE key_name = ?', [self::CONVERSION_LOCK]) !== false;

        return $converted || (int) $this->connection->fetchOne('SELECT COUNT(*) FROM item_category_assignment') === 0;
    }
}
