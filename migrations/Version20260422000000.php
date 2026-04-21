<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Email queue: add max_send_by + provider_dispatched_at, drop redundant send_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_queue ADD max_send_by DATETIME DEFAULT NULL, ADD provider_dispatched_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE email_queue SET provider_dispatched_at = send_at WHERE send_at IS NOT NULL');
        $this->addSql('ALTER TABLE email_queue DROP COLUMN send_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_queue ADD send_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE email_queue SET send_at = provider_dispatched_at WHERE provider_dispatched_at IS NOT NULL');
        $this->addSql('ALTER TABLE email_queue DROP COLUMN provider_dispatched_at, DROP COLUMN max_send_by');
    }
}
