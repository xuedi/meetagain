<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260102200243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multilingual email template translations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_template_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, subject VARCHAR(255) NOT NULL, body LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, email_template_id INT NOT NULL, INDEX IDX_71F8068D131A730F (email_template_id), UNIQUE INDEX UNIQ_71F8068DD4DB71B5131A730F (language, email_template_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_template_translation ADD CONSTRAINT FK_71F8068D131A730F FOREIGN KEY (email_template_id) REFERENCES email_template (id)');

        // Migrate existing data to English translations
        $this->addSql("INSERT INTO email_template_translation (email_template_id, language, subject, body, updated_at) SELECT id, 'en', subject, body, updated_at FROM email_template");

        // Drop the old columns
        $this->addSql('ALTER TABLE announcement RENAME INDEX uniq_4db9d91c1fc998e4 TO UNIQ_4DB9D91C60FFF011');
        $this->addSql('ALTER TABLE email_template DROP subject, DROP body');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_template_translation DROP FOREIGN KEY FK_71F8068D131A730F');
        $this->addSql('DROP TABLE email_template_translation');
        $this->addSql('ALTER TABLE announcement RENAME INDEX uniq_4db9d91c60fff011 TO UNIQ_4DB9D91C1FC998E4');
        $this->addSql('ALTER TABLE email_template ADD subject VARCHAR(255) NOT NULL, ADD body LONGTEXT NOT NULL');
    }
}
