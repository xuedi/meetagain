<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image.alt_translations JSON map for per-language alt text (base alt stays the fallback)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image ADD alt_translations JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE image DROP alt_translations');
    }
}
