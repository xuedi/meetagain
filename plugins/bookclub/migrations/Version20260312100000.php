<?php

declare(strict_types=1);

namespace PluginBookclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove title from book_poll and make event_id mandatory';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_poll DROP COLUMN title, MODIFY event_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_poll ADD title VARCHAR(255) DEFAULT NULL, MODIFY event_id INT DEFAULT NULL');
    }
}
