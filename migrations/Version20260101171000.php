<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_delivery_log table for tracking email delivery status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_delivery_log (id INT AUTO_INCREMENT NOT NULL, email_queue_id INT NOT NULL, sent_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, error_message LONGTEXT DEFAULT NULL, message_id VARCHAR(255) DEFAULT NULL, INDEX IDX_EMAIL_QUEUE (email_queue_id), INDEX idx_email_delivery_log_sent (sent_at), INDEX idx_email_delivery_log_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_delivery_log ADD CONSTRAINT FK_EMAIL_QUEUE FOREIGN KEY (email_queue_id) REFERENCES email_queue (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_delivery_log DROP FOREIGN KEY FK_EMAIL_QUEUE');
        $this->addSql('DROP TABLE email_delivery_log');
    }
}
