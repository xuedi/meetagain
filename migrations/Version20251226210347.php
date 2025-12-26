<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226210347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create email_template table for configurable email templates';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_template (id INT AUTO_INCREMENT NOT NULL, identifier VARCHAR(64) NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, available_variables JSON NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_9C0600CA772E836A (identifier), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE email_template');
    }
}
