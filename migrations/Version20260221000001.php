<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove custom translation system (Translation + TranslationSuggestion tables) — replaced by YAML files';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS translation_suggestion');
        $this->addSql('DROP TABLE IF EXISTS translation');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE translation (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, language VARCHAR(2) NOT NULL, placeholder VARCHAR(255) NOT NULL, translation VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_B469456FA76ED395 (user_id), UNIQUE INDEX UNIQ_B469456FDAEDDE5C83A49A1D (language, placeholder), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE translation_suggestion (id INT AUTO_INCREMENT NOT NULL, created_by_id INT NOT NULL, approved_by_id INT DEFAULT NULL, translation_id INT NOT NULL, language VARCHAR(2) NOT NULL, suggestion LONGTEXT NOT NULL, previous LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_21A9B6C3B03A8386 (created_by_id), INDEX IDX_21A9B6C32D84AF10 (approved_by_id), INDEX IDX_21A9B6C39CAA2B25 (translation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
