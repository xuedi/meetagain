<?php declare(strict_types=1);

namespace ModuleEmailMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Email queue - a nullable claim token and claim time, so two senders never take the same pending row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_email_queue ADD claim_token VARCHAR(32) DEFAULT NULL, ADD claimed_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_email_queue_claim ON mod_email_queue (claim_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_email_queue_claim ON mod_email_queue');
        $this->addSql('ALTER TABLE mod_email_queue DROP claim_token, DROP claimed_at');
    }
}
