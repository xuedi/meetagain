<?php declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the language frontpage: drop the language tile columns and tile images, delete the show_frontpage config row';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language DROP FOREIGN KEY FK_D4DB71B558AEB961');
        $this->addSql('DROP INDEX IDX_D4DB71B558AEB961 ON language');
        $this->addSql('ALTER TABLE language DROP tile_image_id, DROP tile_greeting, DROP tile_intro, DROP tile_cta, DROP tile_image_alt');
        $this->addSql('DELETE FROM image WHERE type = 7');
        $this->addSql("DELETE FROM config WHERE name = 'show_frontpage'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE language ADD tile_image_id INT DEFAULT NULL, ADD tile_greeting VARCHAR(255) DEFAULT NULL, ADD tile_intro LONGTEXT DEFAULT NULL, ADD tile_cta VARCHAR(255) DEFAULT NULL, ADD tile_image_alt VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE language ADD CONSTRAINT FK_D4DB71B558AEB961 FOREIGN KEY (tile_image_id) REFERENCES image (id)');
        $this->addSql('CREATE INDEX IDX_D4DB71B558AEB961 ON language (tile_image_id)');
        $this->addSql("INSERT INTO config (name, value, type) VALUES ('show_frontpage', 'true', 'boolean')");
    }
}
