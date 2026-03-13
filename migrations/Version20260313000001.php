<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add provider_message_id and provider_status columns to email_queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_queue ADD provider_message_id VARCHAR(128) DEFAULT NULL, ADD provider_status VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_email_queue_provider_message_id ON email_queue (provider_message_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_email_queue_provider_message_id ON email_queue');
        $this->addSql('ALTER TABLE email_queue DROP provider_message_id, DROP provider_status');
    }
}
