<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the two generic item taxonomy assignment tables, keyed by (item_type, item_id) with no
 * FK to any plugin entity - the shared item plugins and glossary all participate through plain
 * ints. category_id / tag_id reference definition ids that live in the plugin Config JSON.
 */
final class Version20260720120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create item_category_assignment and item_tag_assignment tables for the shared item taxonomy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE item_category_assignment (
                id INT AUTO_INCREMENT NOT NULL,
                item_type VARCHAR(50) NOT NULL,
                item_id INT NOT NULL,
                category_id INT NOT NULL,
                UNIQUE INDEX uniq_item_category (item_type, item_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE item_tag_assignment (
                id INT AUTO_INCREMENT NOT NULL,
                item_type VARCHAR(50) NOT NULL,
                item_id INT NOT NULL,
                tag_id INT NOT NULL,
                INDEX idx_item_tag_cloud (item_type, tag_id),
                UNIQUE INDEX uniq_item_tag (item_type, item_id, tag_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE item_category_assignment');
        $this->addSql('DROP TABLE item_tag_assignment');
    }
}
