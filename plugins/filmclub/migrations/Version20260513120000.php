<?php declare(strict_types=1);

namespace PluginFilmclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Filmclub 1.0 - consolidated schema replacing all prior filmclub migrations';
    }

    public function up(Schema $schema): void
    {
        // Drop legacy tables from pre-1.0 if present (aggressive refactor, no production data)
        $this->addSql('DROP TABLE IF EXISTS vote_ballot');
        $this->addSql('DROP TABLE IF EXISTS vote');
        $this->addSql('DROP TABLE IF EXISTS film');

        // film
        $this->addSql('CREATE TABLE film (
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
            approved TINYINT(1) NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_8244BE222B29B2B (poster_image_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE film ADD CONSTRAINT FK_8244BE222B29B2B FOREIGN KEY (poster_image_id) REFERENCES image (id) ON DELETE SET NULL');

        // film_poll (no winning_suggestion FK yet - added after film_suggestion is created)
        $this->addSql('CREATE TABLE film_poll (
            id INT AUTO_INCREMENT NOT NULL,
            winning_suggestion_id INT DEFAULT NULL,
            event_id INT NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            end_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            closed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            status INT NOT NULL,
            tied_suggestions JSON DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        // film_suggestion (FK to film and film_poll)
        $this->addSql('CREATE TABLE film_suggestion (
            id INT AUTO_INCREMENT NOT NULL,
            film_id INT NOT NULL,
            poll_id INT DEFAULT NULL,
            suggested_by INT NOT NULL,
            suggested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            resubmit_count INT NOT NULL,
            status INT NOT NULL,
            INDEX IDX_film_suggestion_film (film_id),
            INDEX IDX_film_suggestion_poll (poll_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE film_suggestion ADD CONSTRAINT FK_film_suggestion_film FOREIGN KEY (film_id) REFERENCES film (id)');
        $this->addSql('ALTER TABLE film_suggestion ADD CONSTRAINT FK_film_suggestion_poll FOREIGN KEY (poll_id) REFERENCES film_poll (id) ON DELETE SET NULL');

        // Now add the FK from film_poll.winning_suggestion_id to film_suggestion
        $this->addSql('ALTER TABLE film_poll ADD CONSTRAINT FK_film_poll_winning_suggestion FOREIGN KEY (winning_suggestion_id) REFERENCES film_suggestion (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_film_poll_winning_suggestion ON film_poll (winning_suggestion_id)');

        // film_poll_vote (multi-choice; unique on poll+user+suggestion)
        $this->addSql('CREATE TABLE film_poll_vote (
            id INT AUTO_INCREMENT NOT NULL,
            poll_id INT NOT NULL,
            suggestion_id INT NOT NULL,
            user_id INT NOT NULL,
            voted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_film_poll_vote_poll (poll_id),
            INDEX IDX_film_poll_vote_suggestion (suggestion_id),
            UNIQUE INDEX unique_poll_user_suggestion (poll_id, user_id, suggestion_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE film_poll_vote ADD CONSTRAINT FK_film_poll_vote_poll FOREIGN KEY (poll_id) REFERENCES film_poll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE film_poll_vote ADD CONSTRAINT FK_film_poll_vote_suggestion FOREIGN KEY (suggestion_id) REFERENCES film_suggestion (id)');

        // film_note
        $this->addSql('CREATE TABLE film_note (
            id INT AUTO_INCREMENT NOT NULL,
            film_id INT NOT NULL,
            user_id INT NOT NULL,
            body LONGTEXT NOT NULL,
            reveal_to_group TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_film_note_film (film_id),
            UNIQUE INDEX unique_user_film_note (user_id, film_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE film_note ADD CONSTRAINT FK_film_note_film FOREIGN KEY (film_id) REFERENCES film (id)');

        // film_selection
        $this->addSql('CREATE TABLE film_selection (
            id INT AUTO_INCREMENT NOT NULL,
            film_id INT NOT NULL,
            event_id INT NOT NULL,
            selected_by INT NOT NULL,
            selected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_film_selection_film (film_id),
            INDEX IDX_film_selection_event (event_id),
            UNIQUE INDEX unique_event_film_selection (event_id, film_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE film_selection ADD CONSTRAINT FK_film_selection_film FOREIGN KEY (film_id) REFERENCES film (id)');
        $this->addSql('ALTER TABLE film_selection ADD CONSTRAINT FK_film_selection_event FOREIGN KEY (event_id) REFERENCES event (id)');

        // film_wishlist_entry
        $this->addSql('CREATE TABLE film_wishlist_entry (
            id INT AUTO_INCREMENT NOT NULL,
            film_id INT NOT NULL,
            user_id INT NOT NULL,
            priority_counter INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_film_wishlist_film (film_id),
            UNIQUE INDEX unique_user_film_wishlist (user_id, film_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE film_wishlist_entry ADD CONSTRAINT FK_film_wishlist_film FOREIGN KEY (film_id) REFERENCES film (id)');

        // filmclub_group_settings (group_id is a plain int - no FK, keeps filmclub independent of other plugins)
        $this->addSql('CREATE TABLE filmclub_group_settings (
            id INT AUTO_INCREMENT NOT NULL,
            group_id INT NOT NULL,
            adapter VARCHAR(10) DEFAULT NULL,
            encrypted_tmdb_key LONGTEXT DEFAULT NULL,
            encrypted_omdb_key LONGTEXT DEFAULT NULL,
            default_poll_duration_days INT NOT NULL DEFAULT 7,
            UNIQUE INDEX unique_filmclub_group (group_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE filmclub_group_settings DROP INDEX unique_filmclub_group');
        $this->addSql('DROP TABLE filmclub_group_settings');

        $this->addSql('ALTER TABLE film_wishlist_entry DROP FOREIGN KEY FK_film_wishlist_film');
        $this->addSql('DROP TABLE film_wishlist_entry');

        $this->addSql('ALTER TABLE film_selection DROP FOREIGN KEY FK_film_selection_film');
        $this->addSql('ALTER TABLE film_selection DROP FOREIGN KEY FK_film_selection_event');
        $this->addSql('DROP TABLE film_selection');

        $this->addSql('ALTER TABLE film_note DROP FOREIGN KEY FK_film_note_film');
        $this->addSql('DROP TABLE film_note');

        $this->addSql('ALTER TABLE film_poll_vote DROP FOREIGN KEY FK_film_poll_vote_poll');
        $this->addSql('ALTER TABLE film_poll_vote DROP FOREIGN KEY FK_film_poll_vote_suggestion');
        $this->addSql('DROP TABLE film_poll_vote');

        $this->addSql('ALTER TABLE film_poll DROP FOREIGN KEY FK_film_poll_winning_suggestion');
        $this->addSql('ALTER TABLE film_suggestion DROP FOREIGN KEY FK_film_suggestion_poll');
        $this->addSql('ALTER TABLE film_suggestion DROP FOREIGN KEY FK_film_suggestion_film');
        $this->addSql('DROP TABLE film_suggestion');
        $this->addSql('DROP TABLE film_poll');

        $this->addSql('ALTER TABLE film DROP FOREIGN KEY FK_8244BE222B29B2B');
        $this->addSql('DROP TABLE film');
    }
}
