<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226221548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create language table for database-driven language configuration';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE language (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(2) NOT NULL, name VARCHAR(64) NOT NULL, enabled TINYINT NOT NULL, sort_order INT NOT NULL, UNIQUE INDEX UNIQ_D4DB71B577153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO language (code, name, enabled, sort_order) VALUES ('en', 'English', 1, 1), ('de', 'German', 1, 2), ('cn', 'Chinese', 1, 3)");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE language');
    }
}
