<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403190001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy datetime_immutable COMMENT annotations from core tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event CHANGE rsvp_notification_sent_at rsvp_notification_sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE support_request CHANGE contact_type contact_type VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event CHANGE rsvp_notification_sent_at rsvp_notification_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE support_request CHANGE contact_type contact_type VARCHAR(20) DEFAULT \'general\' NOT NULL');
    }
}
