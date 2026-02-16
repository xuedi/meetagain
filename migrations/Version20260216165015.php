<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216165015 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add send_rsvp_notifications config option';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT IGNORE INTO config (name, value, type) VALUES ('send_rsvp_notifications', 'false', 'boolean')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM config WHERE name = 'send_rsvp_notifications'");
    }
}
