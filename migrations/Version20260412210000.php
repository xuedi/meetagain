<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add send_event_reminders and send_upcoming_digest boolean config settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO config (name, value, type) VALUES ('send_event_reminders', 'false', 'boolean')");
        $this->addSql("INSERT INTO config (name, value, type) VALUES ('send_upcoming_digest', 'false', 'boolean')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM config WHERE name IN ('send_event_reminders', 'send_upcoming_digest')");
    }
}
