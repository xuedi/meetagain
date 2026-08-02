<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the topic table backing the town hall forum tree';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE topic (
                id INT AUTO_INCREMENT NOT NULL,
                parent_id INT DEFAULT NULL,
                author_id INT DEFAULT NULL,
                title VARCHAR(120) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_9D40DE1B727ACA70 (parent_id),
                INDEX IDX_9D40DE1BF675F31B (author_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1B727ACA70 FOREIGN KEY (parent_id) REFERENCES topic (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1BF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE topic DROP FOREIGN KEY FK_9D40DE1B727ACA70');
        $this->addSql('ALTER TABLE topic DROP FOREIGN KEY FK_9D40DE1BF675F31B');
        $this->addSql('DROP TABLE topic');
    }
}
