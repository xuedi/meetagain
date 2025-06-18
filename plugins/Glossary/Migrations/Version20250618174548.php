<?php declare(strict_types=1);

namespace Plugin\Glossary\DoctrineMigrationsGlossary;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250618174548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE glossary (id INT AUTO_INCREMENT NOT NULL, phrase VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, created_by INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE glossary
        SQL);
    }
}
