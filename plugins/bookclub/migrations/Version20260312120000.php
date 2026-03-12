<?php

declare(strict_types=1);

namespace PluginBookclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260312120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop group_id from book_suggestion - group scoping moved to multisite mapping table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_suggestion DROP COLUMN IF EXISTS group_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book_suggestion ADD group_id INT DEFAULT NULL');
    }
}
