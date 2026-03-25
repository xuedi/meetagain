<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325224341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contact_type column to support_request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE support_request ADD contact_type VARCHAR(20) NOT NULL DEFAULT 'general'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE support_request DROP contact_type');
    }
}
