<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_delivery_sync_enabled config setting (default false)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO config (name, value, type) VALUES ('email_delivery_sync_enabled', 'false', 'boolean')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM config WHERE name = 'email_delivery_sync_enabled'");
    }
}
