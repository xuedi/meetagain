<?php declare(strict_types=1);

namespace ModuleEmailMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Email queue - an index on status and creation time for the nightly retention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_email_queue_status_created ON mod_email_queue (status, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_email_queue_status_created ON mod_email_queue');
    }
}
