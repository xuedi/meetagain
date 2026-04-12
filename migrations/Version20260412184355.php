<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412184355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix cron_log run_at column and index to match Doctrine schema expectations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cron_log CHANGE run_at run_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE cron_log RENAME INDEX idx_cron_log_run_at TO IDX_7C0163B3518D64A8');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cron_log CHANGE run_at run_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cron_log RENAME INDEX IDX_7C0163B3518D64A8 TO idx_cron_log_run_at');
    }
}
