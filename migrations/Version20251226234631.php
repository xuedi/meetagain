<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226234631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tile_image_id column to language table for frontpage tiles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language ADD tile_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE language ADD CONSTRAINT FK_D4DB71B558AEB961 FOREIGN KEY (tile_image_id) REFERENCES image (id)');
        $this->addSql('CREATE INDEX IDX_D4DB71B558AEB961 ON language (tile_image_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language DROP FOREIGN KEY FK_D4DB71B558AEB961');
        $this->addSql('DROP INDEX IDX_D4DB71B558AEB961 ON language');
        $this->addSql('ALTER TABLE language DROP tile_image_id');
    }
}
