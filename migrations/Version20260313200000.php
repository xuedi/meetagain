<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rsvp_notification_sent_at column to event table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE event ADD rsvp_notification_sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP rsvp_notification_sent_at');
    }
}
