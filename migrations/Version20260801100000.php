<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the item_tag table holding the per-item-type tag vocabulary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE item_tag (
                id INT AUTO_INCREMENT NOT NULL,
                parent_id INT DEFAULT NULL,
                item_type VARCHAR(50) NOT NULL,
                position INT NOT NULL,
                labels JSON NOT NULL,
                INDEX idx_item_tag_type (item_type, position),
                INDEX IDX_E49CCCB1727ACA70 (parent_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE item_tag ADD CONSTRAINT FK_E49CCCB1727ACA70 FOREIGN KEY (parent_id) REFERENCES item_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_tag DROP FOREIGN KEY FK_E49CCCB1727ACA70');
        $this->addSql('DROP TABLE item_tag');
    }
}
