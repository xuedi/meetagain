<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a queued email carry file attachments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_queue ADD attachments JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_queue DROP attachments');
    }
}
