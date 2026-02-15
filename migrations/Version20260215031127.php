<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215031127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor CMS menu locations from JSON column to join entity relationship';
    }

    public function up(Schema $schema): void
    {
        // Create new cms_menu_location table
        $this->addSql('CREATE TABLE cms_menu_location (id INT AUTO_INCREMENT NOT NULL, location INT NOT NULL, cms_id INT NOT NULL, INDEX IDX_C29E7788BE8A7CFB (cms_id), UNIQUE INDEX unique_cms_location (cms_id, location), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cms_menu_location ADD CONSTRAINT FK_C29E7788BE8A7CFB FOREIGN KEY (cms_id) REFERENCES cms (id)');

        // Drop old JSON column (data will be set fresh via fixtures or manually in production)
        $this->addSql('ALTER TABLE cms DROP menu_locations, CHANGE locked locked TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Restore JSON column (data will be lost, needs to be set manually)
        $this->addSql('ALTER TABLE cms ADD menu_locations JSON DEFAULT NULL, CHANGE locked locked TINYINT DEFAULT 0 NOT NULL');

        // Drop join table
        $this->addSql('ALTER TABLE cms_menu_location DROP FOREIGN KEY FK_C29E7788BE8A7CFB');
        $this->addSql('DROP TABLE cms_menu_location');
    }
}
