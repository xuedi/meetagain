<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250823210628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'added suggestions for changing translations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE translation_suggestion (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, approved_at DATETIME DEFAULT NULL, language VARCHAR(2) NOT NULL, suggestion LONGTEXT NOT NULL, created_by_id INT NOT NULL, approved_by_id INT DEFAULT NULL, translation_id INT NOT NULL, INDEX IDX_A70842DBB03A8386 (created_by_id), INDEX IDX_A70842DB2D234F6A (approved_by_id), INDEX IDX_A70842DB9CAA2B25 (translation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE translation_suggestion ADD CONSTRAINT FK_A70842DBB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE translation_suggestion ADD CONSTRAINT FK_A70842DB2D234F6A FOREIGN KEY (approved_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE translation_suggestion ADD CONSTRAINT FK_A70842DB9CAA2B25 FOREIGN KEY (translation_id) REFERENCES translation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE translation_suggestion DROP FOREIGN KEY FK_A70842DBB03A8386');
        $this->addSql('ALTER TABLE translation_suggestion DROP FOREIGN KEY FK_A70842DB2D234F6A');
        $this->addSql('ALTER TABLE translation_suggestion DROP FOREIGN KEY FK_A70842DB9CAA2B25');
        $this->addSql('DROP TABLE translation_suggestion');
    }
}
