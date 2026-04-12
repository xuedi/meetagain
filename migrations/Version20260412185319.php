<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412185319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add duration_ms column to cron_log for tracking total run time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cron_log ADD duration_ms INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cron_log DROP duration_ms');
    }
}
