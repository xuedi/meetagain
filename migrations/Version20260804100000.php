<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add editable per-language tile text (greeting, intro, CTA, image alt) to the language table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language ADD tile_greeting VARCHAR(255) DEFAULT NULL, ADD tile_intro LONGTEXT DEFAULT NULL, ADD tile_cta VARCHAR(255) DEFAULT NULL, ADD tile_image_alt VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language DROP tile_greeting, DROP tile_intro, DROP tile_cta, DROP tile_image_alt');
    }
}
