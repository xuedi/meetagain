<?php declare(strict_types=1);

namespace PluginKaraokeMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923201211 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Karaoke - songs with an embedded video, lyric lines and per-language line translations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plg_karaoke_song (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            artist VARCHAR(255) DEFAULT NULL,
            language VARCHAR(2) NOT NULL,
            media_provider VARCHAR(255) NOT NULL,
            media_id VARCHAR(64) NOT NULL,
            offset_ms INT DEFAULT 0 NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE plg_karaoke_lyric_line (
            id INT AUTO_INCREMENT NOT NULL,
            song_id INT NOT NULL,
            position INT NOT NULL,
            start_ms INT DEFAULT NULL,
            text VARCHAR(500) NOT NULL,
            UNIQUE INDEX uniq_karaoke_line_song_position (song_id, position),
            INDEX IDX_42D33B56A0BDB2F3 (song_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE plg_karaoke_line_translation (
            id INT AUTO_INCREMENT NOT NULL,
            line_id INT NOT NULL,
            language VARCHAR(2) NOT NULL,
            text VARCHAR(500) NOT NULL,
            UNIQUE INDEX uniq_karaoke_translation_lang_line (language, line_id),
            INDEX IDX_6AFBA3DC4D7B7542 (line_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE plg_karaoke_lyric_line ADD CONSTRAINT FK_42D33B56A0BDB2F3 FOREIGN KEY (song_id) REFERENCES plg_karaoke_song (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE plg_karaoke_line_translation ADD CONSTRAINT FK_6AFBA3DC4D7B7542 FOREIGN KEY (line_id) REFERENCES plg_karaoke_lyric_line (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_karaoke_line_translation DROP FOREIGN KEY FK_6AFBA3DC4D7B7542');
        $this->addSql('ALTER TABLE plg_karaoke_lyric_line DROP FOREIGN KEY FK_42D33B56A0BDB2F3');
        $this->addSql('DROP TABLE plg_karaoke_line_translation');
        $this->addSql('DROP TABLE plg_karaoke_lyric_line');
        $this->addSql('DROP TABLE plg_karaoke_song');
    }
}
