<?php

declare(strict_types=1);

namespace PluginFilmsMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Films 1.0 - film catalog and global lookup settings (rebuilt from filmclub, no production data)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plg_films_film (
            id INT AUTO_INCREMENT NOT NULL,
            poster_image_id INT DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            original_title VARCHAR(255) DEFAULT NULL,
            year INT DEFAULT NULL,
            runtime INT DEFAULT NULL,
            external_id VARCHAR(255) DEFAULT NULL,
            external_source VARCHAR(10) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            genres JSON NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_CDF89810DE75329 (poster_image_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE plg_films_film ADD CONSTRAINT FK_CDF89810DE75329 FOREIGN KEY (poster_image_id) REFERENCES image (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE plg_films_settings (
            id INT AUTO_INCREMENT NOT NULL,
            adapter VARCHAR(10) DEFAULT NULL,
            encrypted_tmdb_key LONGTEXT DEFAULT NULL,
            encrypted_omdb_key LONGTEXT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE plg_films_settings');
        $this->addSql('ALTER TABLE plg_films_film DROP FOREIGN KEY FK_CDF89810DE75329');
        $this->addSql('DROP TABLE plg_films_film');
    }
}
