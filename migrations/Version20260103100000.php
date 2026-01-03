<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260103100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_id column to book_poll table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_poll ADD event_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_poll DROP event_id');
    }
}
