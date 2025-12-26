<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226210641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rendered_body to email_queue and make template nullable for database-driven emails';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_queue ADD rendered_body LONGTEXT DEFAULT NULL, CHANGE template template VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_queue DROP rendered_body, CHANGE template template VARCHAR(255) NOT NULL');
    }
}
