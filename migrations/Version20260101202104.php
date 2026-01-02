<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101202104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE announcement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, recipient_count INT DEFAULT NULL, created_by_id INT NOT NULL, cms_page_id INT DEFAULT NULL, INDEX IDX_4DB9D91CB03A8386 (created_by_id), INDEX IDX_4DB9D91C52AA6CF5 (cms_page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE announcement ADD CONSTRAINT FK_4DB9D91CB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE announcement ADD CONSTRAINT FK_4DB9D91C52AA6CF5 FOREIGN KEY (cms_page_id) REFERENCES cms (id)');
        $this->addSql('ALTER TABLE email_queue CHANGE template template VARCHAR(64) DEFAULT NULL, CHANGE status status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE event CHANGE canceled canceled TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcement DROP FOREIGN KEY FK_4DB9D91CB03A8386');
        $this->addSql('ALTER TABLE announcement DROP FOREIGN KEY FK_4DB9D91C52AA6CF5');
        $this->addSql('DROP TABLE announcement');
        $this->addSql('ALTER TABLE email_queue CHANGE status status VARCHAR(20) DEFAULT \'pending\' NOT NULL, CHANGE template template VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE event CHANGE canceled canceled TINYINT DEFAULT 0 NOT NULL');
    }
}
