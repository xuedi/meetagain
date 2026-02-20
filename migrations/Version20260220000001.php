<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace published bool with EventStatus enum (draft/published/locked)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE event ADD status VARCHAR(20) NOT NULL DEFAULT 'draft'");
        $this->addSql("UPDATE event SET status = 'draft' WHERE published = 0");
        $this->addSql("UPDATE event SET status = 'published' WHERE published = 1");
        $this->addSql('ALTER TABLE event DROP COLUMN published');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD published TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql("UPDATE event SET published = 0 WHERE status = 'draft'");
        $this->addSql("UPDATE event SET published = 1 WHERE status IN ('published', 'locked')");
        $this->addSql('ALTER TABLE event DROP COLUMN status');
    }
}
