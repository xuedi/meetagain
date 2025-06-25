<?php

declare(strict_types=1);

namespace Plugin\Glossary\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250625204637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE glossary (id INT AUTO_INCREMENT NOT NULL, phrase VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, created_by INT NOT NULL, category_id INT NOT NULL, INDEX IDX_B0850B4312469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE glossary_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, created_by INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE glossary ADD CONSTRAINT FK_B0850B4312469DE2 FOREIGN KEY (category_id) REFERENCES glossary_category (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE glossary DROP FOREIGN KEY FK_B0850B4312469DE2
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE glossary
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE glossary_category
        SQL);
    }
}
