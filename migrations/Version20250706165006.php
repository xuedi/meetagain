<?php

declare(strict_types=1);

namespace AppMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250706165006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'merged migration to current state';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, type INT NOT NULL, meta JSON DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_AC74095AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cms (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL, published TINYINT(1) NOT NULL, created_by_id INT NOT NULL, INDEX IDX_AC8F9907B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cms_block (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, type INT NOT NULL, json JSON NOT NULL, priority DOUBLE PRECISION NOT NULL, page_id INT NOT NULL, image_id INT DEFAULT NULL, INDEX IDX_AD680C0EC4663E4 (page_id), INDEX IDX_AD680C0E3DA5256D (image_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, content LONGTEXT NOT NULL, event_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9474526C71F7E88B (event_id), INDEX IDX_9474526CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE config (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) NOT NULL, value VARCHAR(128) NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE email_queue (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, send_at DATETIME DEFAULT NULL, subject VARCHAR(255) NOT NULL, sender VARCHAR(255) NOT NULL, recipient VARCHAR(255) NOT NULL, lang VARCHAR(2) NOT NULL, context JSON NOT NULL, template VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, initial TINYINT(1) NOT NULL, start DATETIME NOT NULL, stop DATETIME DEFAULT NULL, recurring_of INT DEFAULT NULL, recurring_rule INT DEFAULT NULL, created_at DATETIME NOT NULL, type INT DEFAULT NULL, published TINYINT(1) NOT NULL, featured TINYINT(1) NOT NULL, user_id INT NOT NULL, location_id INT NOT NULL, preview_image_id INT DEFAULT NULL, INDEX IDX_3BAE0AA7A76ED395 (user_id), INDEX IDX_3BAE0AA764D218E (location_id), INDEX IDX_3BAE0AA7FAE957CD (preview_image_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE event_host (event_id INT NOT NULL, host_id INT NOT NULL, INDEX IDX_D0EC5F5671F7E88B (event_id), INDEX IDX_D0EC5F561FB8D185 (host_id), PRIMARY KEY(event_id, host_id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE event_rsvp (event_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_806E82E571F7E88B (event_id), INDEX IDX_806E82E5A76ED395 (user_id), PRIMARY KEY(event_id, user_id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE event_translation (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(2) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, event_id INT NOT NULL, INDEX IDX_1FE096EF71F7E88B (event_id), UNIQUE INDEX UNIQ_1FE096EFD4DB71B571F7E88B (language, event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE host (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(16) NOT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_CF2713FDA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE image (id INT AUTO_INCREMENT NOT NULL, mime_type VARCHAR(128) NOT NULL, extension VARCHAR(8) NOT NULL, size BIGINT NOT NULL, hash VARCHAR(64) NOT NULL, alt VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, type INT NOT NULL, reported INT DEFAULT NULL, uploader_id INT NOT NULL, event_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_C53D045FD1B862B8 (hash), INDEX IDX_C53D045F16678C77 (uploader_id), INDEX IDX_C53D045F71F7E88B (event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE location (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, street VARCHAR(255) NOT NULL, city VARCHAR(32) NOT NULL, postcode VARCHAR(8) NOT NULL, created_at DATETIME NOT NULL, longitude VARCHAR(20) DEFAULT NULL, latitude VARCHAR(20) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_5E9E89CBA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, content LONGTEXT NOT NULL, deleted TINYINT(1) NOT NULL, was_read TINYINT(1) NOT NULL, sender_id INT NOT NULL, receiver_id INT NOT NULL, INDEX IDX_B6BD307FF624B39D (sender_id), INDEX IDX_B6BD307FCD53EDB6 (receiver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE not_found_log (id INT AUTO_INCREMENT NOT NULL, url VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, ip VARCHAR(16) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE plugin (id INT AUTO_INCREMENT NOT NULL, version VARCHAR(16) NOT NULL, name VARCHAR(16) NOT NULL, description VARCHAR(255) NOT NULL, installed TINYINT(1) DEFAULT 0 NOT NULL, enabled TINYINT(1) DEFAULT 0 NOT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE translation (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, language VARCHAR(2) NOT NULL, placeholder VARCHAR(255) NOT NULL, translation VARCHAR(255) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_B469456FA76ED395 (user_id), UNIQUE INDEX UNIQ_B469456FD4DB71B5F5E69F02 (language, placeholder), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_login DATETIME NOT NULL, locale VARCHAR(2) NOT NULL, status INT NOT NULL, public TINYINT(1) NOT NULL, regcode VARCHAR(40) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, verified TINYINT(1) NOT NULL, restricted TINYINT(1) NOT NULL, osm_consent TINYINT(1) NOT NULL, tagging TINYINT(1) NOT NULL, notification TINYINT(1) NOT NULL, notification_settings JSON DEFAULT NULL, image_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D6493DA5256D (image_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_user (user_source INT NOT NULL, user_target INT NOT NULL, INDEX IDX_F7129A803AD8644E (user_source), INDEX IDX_F7129A80233D34C1 (user_target), PRIMARY KEY(user_source, user_target)) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE activity ADD CONSTRAINT FK_AC74095AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cms ADD CONSTRAINT FK_AC8F9907B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cms_block ADD CONSTRAINT FK_AD680C0EC4663E4 FOREIGN KEY (page_id) REFERENCES cms (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cms_block ADD CONSTRAINT FK_AD680C0E3DA5256D FOREIGN KEY (image_id) REFERENCES image (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment ADD CONSTRAINT FK_9474526C71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA764D218E FOREIGN KEY (location_id) REFERENCES location (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7FAE957CD FOREIGN KEY (preview_image_id) REFERENCES image (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_host ADD CONSTRAINT FK_D0EC5F5671F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_host ADD CONSTRAINT FK_D0EC5F561FB8D185 FOREIGN KEY (host_id) REFERENCES host (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_rsvp ADD CONSTRAINT FK_806E82E571F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_rsvp ADD CONSTRAINT FK_806E82E5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_translation ADD CONSTRAINT FK_1FE096EF71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE host ADD CONSTRAINT FK_CF2713FDA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image ADD CONSTRAINT FK_C53D045F16678C77 FOREIGN KEY (uploader_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image ADD CONSTRAINT FK_C53D045F71F7E88B FOREIGN KEY (event_id) REFERENCES event (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE location ADD CONSTRAINT FK_5E9E89CBA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message ADD CONSTRAINT FK_B6BD307FCD53EDB6 FOREIGN KEY (receiver_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE translation ADD CONSTRAINT FK_B469456FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` ADD CONSTRAINT FK_8D93D6493DA5256D FOREIGN KEY (image_id) REFERENCES image (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_user ADD CONSTRAINT FK_F7129A803AD8644E FOREIGN KEY (user_source) REFERENCES `user` (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_user ADD CONSTRAINT FK_F7129A80233D34C1 FOREIGN KEY (user_target) REFERENCES `user` (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE activity DROP FOREIGN KEY FK_AC74095AA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cms DROP FOREIGN KEY FK_AC8F9907B03A8386
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cms_block DROP FOREIGN KEY FK_AD680C0EC4663E4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cms_block DROP FOREIGN KEY FK_AD680C0E3DA5256D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment DROP FOREIGN KEY FK_9474526C71F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA764D218E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA7FAE957CD
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_host DROP FOREIGN KEY FK_D0EC5F5671F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_host DROP FOREIGN KEY FK_D0EC5F561FB8D185
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_rsvp DROP FOREIGN KEY FK_806E82E571F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_rsvp DROP FOREIGN KEY FK_806E82E5A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE event_translation DROP FOREIGN KEY FK_1FE096EF71F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE host DROP FOREIGN KEY FK_CF2713FDA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image DROP FOREIGN KEY FK_C53D045F16678C77
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE image DROP FOREIGN KEY FK_C53D045F71F7E88B
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE location DROP FOREIGN KEY FK_5E9E89CBA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FCD53EDB6
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE translation DROP FOREIGN KEY FK_B469456FA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D6493DA5256D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_user DROP FOREIGN KEY FK_F7129A803AD8644E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_user DROP FOREIGN KEY FK_F7129A80233D34C1
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE activity
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE cms
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE cms_block
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE comment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE config
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE email_queue
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE event
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE event_host
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE event_rsvp
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE event_translation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE host
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE image
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE location
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE message
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE not_found_log
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE plugin
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE translation
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE `user`
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_user
        SQL);
    }
}
