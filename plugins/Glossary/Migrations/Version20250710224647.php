<?php

declare(strict_types=1);

namespace PluginGlossaryMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250710224647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'initial glossary structure';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE glossary (id INT AUTO_INCREMENT NOT NULL, phrase VARCHAR(255) NOT NULL, pinyin VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, created_by INT NOT NULL, approved TINYINT(1) NOT NULL, category INT NOT NULL, suggestion JSON DEFAULT NULL, explanation LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE glossary
        SQL);
    }
}
