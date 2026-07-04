<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add attribution and attribution_not_required columns to image for the image-attribution feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ADD attribution VARCHAR(500) DEFAULT NULL, ADD attribution_not_required TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP COLUMN attribution, DROP COLUMN attribution_not_required');
    }
}
