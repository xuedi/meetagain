<?php

declare(strict_types=1);

namespace PluginDishesMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250811202038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'added preview image';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dish ADD preview_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE dish ADD CONSTRAINT FK_957D8CB8FAE957CD FOREIGN KEY (preview_image_id) REFERENCES image (id)');
        $this->addSql('CREATE INDEX IDX_957D8CB8FAE957CD ON dish (preview_image_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dish DROP FOREIGN KEY FK_957D8CB8FAE957CD');
        $this->addSql('DROP INDEX IDX_957D8CB8FAE957CD ON dish');
        $this->addSql('ALTER TABLE dish DROP preview_image_id');
    }
}
