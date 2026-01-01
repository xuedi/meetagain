<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status and error_message fields to email_queue table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE email_queue ADD status VARCHAR(20) NOT NULL DEFAULT 'pending', ADD error_message LONGTEXT DEFAULT NULL");
        $this->addSql("UPDATE email_queue SET status = 'sent' WHERE send_at IS NOT NULL");
        $this->addSql('CREATE INDEX idx_email_queue_status ON email_queue (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_email_queue_status ON email_queue');
        $this->addSql('ALTER TABLE email_queue DROP status, DROP error_message');
    }
}
