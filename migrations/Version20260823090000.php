<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260823090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds event.external_rsvp, the organizer-maintained headcount of attendees who signed up somewhere other than this site.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD external_rsvp INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP external_rsvp');
    }
}
