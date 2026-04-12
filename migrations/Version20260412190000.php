<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_reminder_sent_at to event table for reminder deduplication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE event ADD event_reminder_sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP event_reminder_sent_at');
    }
}
