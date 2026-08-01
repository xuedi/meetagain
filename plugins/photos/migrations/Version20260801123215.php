<?php

declare(strict_types=1);

namespace PluginPhotosMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801123215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photos 1.0 - member photo uploads with per-language texts and denormalized camera metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plg_photos_photo (
            id INT AUTO_INCREMENT NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL,
            taken_at DATETIME DEFAULT NULL,
            meta JSON DEFAULT NULL,
            image_id INT NOT NULL,
            INDEX IDX_4B0459CD3DA5256D (image_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE plg_photos_photo_translation (
            id INT AUTO_INCREMENT NOT NULL,
            language VARCHAR(2) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            photo_id INT NOT NULL,
            INDEX IDX_7200F06E7E9E4C8C (photo_id),
            UNIQUE INDEX uniq_photos_translation_lang_photo (language, photo_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE plg_photos_photo ADD CONSTRAINT FK_4B0459CD3DA5256D FOREIGN KEY (image_id) REFERENCES image (id)');
        $this->addSql('ALTER TABLE plg_photos_photo_translation ADD CONSTRAINT FK_7200F06E7E9E4C8C FOREIGN KEY (photo_id) REFERENCES plg_photos_photo (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_photos_photo DROP FOREIGN KEY FK_4B0459CD3DA5256D');
        $this->addSql('ALTER TABLE plg_photos_photo_translation DROP FOREIGN KEY FK_7200F06E7E9E4C8C');
        $this->addSql('DROP TABLE plg_photos_photo_translation');
        $this->addSql('DROP TABLE plg_photos_photo');
    }
}
