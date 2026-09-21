<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921162519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message.reminder_sent_at for the delayed unread-message email, backfilled for existing unread messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD reminder_sent_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE message SET reminder_sent_at = created_at WHERE was_read = 0');
        $this->addSql('CREATE INDEX idx_message_reminder_due ON message (was_read, reminder_sent_at, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_message_reminder_due ON message');
        $this->addSql('ALTER TABLE message DROP reminder_sent_at');
    }
}
