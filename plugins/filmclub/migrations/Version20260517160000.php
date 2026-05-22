<?php declare(strict_types=1);

namespace PluginFilmclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop FilmSuggestion: remove film_suggestion table, add film_poll_films join table, promote event FK on film_poll, rewire film_poll_vote to film';
    }

    public function up(Schema $schema): void
    {
        // Drop inbound FKs that reference film_suggestion before dropping the table itself.
        $this->addSql('ALTER TABLE film_poll DROP FOREIGN KEY `FK_film_poll_winning_suggestion`');
        $this->addSql('DROP INDEX IDX_film_poll_winning_suggestion ON film_poll');
        $this->addSql('ALTER TABLE film_poll_vote DROP FOREIGN KEY `FK_film_poll_vote_suggestion`');
        $this->addSql('DROP INDEX IDX_film_poll_vote_suggestion ON film_poll_vote');
        $this->addSql('DROP INDEX unique_poll_user_suggestion ON film_poll_vote');

        // Drop outbound FKs on film_suggestion, then drop the table.
        $this->addSql('ALTER TABLE film_suggestion DROP FOREIGN KEY `FK_film_suggestion_film`');
        $this->addSql('ALTER TABLE film_suggestion DROP FOREIGN KEY `FK_film_suggestion_poll`');
        $this->addSql('DROP TABLE film_suggestion');

        // Create the film_poll_films join table. film_id is RESTRICT: deleting a film referenced by a poll's
        // candidate set must be blocked, not silently propagated (film is a shared entity).
        $this->addSql('CREATE TABLE film_poll_films (poll_id INT NOT NULL, film_id INT NOT NULL, INDEX IDX_17B91AD83C947C0F (poll_id), INDEX IDX_17B91AD8567F5183 (film_id), PRIMARY KEY (poll_id, film_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE film_poll_films ADD CONSTRAINT FK_17B91AD83C947C0F FOREIGN KEY (poll_id) REFERENCES film_poll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE film_poll_films ADD CONSTRAINT FK_17B91AD8567F5183 FOREIGN KEY (film_id) REFERENCES film (id)');

        // Reshape film_poll: rename suggestion columns, promote event_id to a real FK, add winning_film FK.
        $this->addSql('ALTER TABLE film_poll CHANGE tied_suggestions tied_film_ids JSON DEFAULT NULL, CHANGE winning_suggestion_id winning_film_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE film_poll ADD CONSTRAINT FK_E113104671F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE film_poll ADD CONSTRAINT FK_E1131046C6D3D044 FOREIGN KEY (winning_film_id) REFERENCES film (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E113104671F7E88B ON film_poll (event_id)');
        $this->addSql('CREATE INDEX IDX_E1131046C6D3D044 ON film_poll (winning_film_id)');

        // Reshape film_poll_vote: rename suggestion_id to film_id, add film FK (RESTRICT per shared-entity rule).
        $this->addSql('ALTER TABLE film_poll_vote CHANGE suggestion_id film_id INT NOT NULL');
        $this->addSql('ALTER TABLE film_poll_vote ADD CONSTRAINT FK_D99AD08D567F5183 FOREIGN KEY (film_id) REFERENCES film (id)');
        $this->addSql('CREATE INDEX IDX_D99AD08D567F5183 ON film_poll_vote (film_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_poll_user_film ON film_poll_vote (poll_id, user_id, film_id)');
    }

    public function down(Schema $schema): void
    {
        // Recreate film_suggestion table
        $this->addSql('CREATE TABLE film_suggestion (id INT AUTO_INCREMENT NOT NULL, film_id INT NOT NULL, poll_id INT DEFAULT NULL, suggested_by INT NOT NULL, suggested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', resubmit_count INT NOT NULL, status INT NOT NULL, INDEX IDX_film_suggestion_poll (poll_id), INDEX IDX_film_suggestion_film (film_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE film_suggestion ADD CONSTRAINT `FK_film_suggestion_film` FOREIGN KEY (film_id) REFERENCES film (id)');
        $this->addSql('ALTER TABLE film_suggestion ADD CONSTRAINT `FK_film_suggestion_poll` FOREIGN KEY (poll_id) REFERENCES film_poll (id) ON DELETE SET NULL');

        // Drop film_poll_films join table
        $this->addSql('ALTER TABLE film_poll_films DROP FOREIGN KEY FK_17B91AD83C947C0F');
        $this->addSql('ALTER TABLE film_poll_films DROP FOREIGN KEY FK_17B91AD8567F5183');
        $this->addSql('DROP TABLE film_poll_films');

        // Revert film_poll
        $this->addSql('ALTER TABLE film_poll DROP FOREIGN KEY FK_E113104671F7E88B');
        $this->addSql('ALTER TABLE film_poll DROP FOREIGN KEY FK_E1131046C6D3D044');
        $this->addSql('DROP INDEX IDX_E113104671F7E88B ON film_poll');
        $this->addSql('DROP INDEX IDX_E1131046C6D3D044 ON film_poll');
        $this->addSql('ALTER TABLE film_poll CHANGE winning_film_id winning_suggestion_id INT DEFAULT NULL, CHANGE tied_film_ids tied_suggestions JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE film_poll ADD CONSTRAINT `FK_film_poll_winning_suggestion` FOREIGN KEY (winning_suggestion_id) REFERENCES film_suggestion (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_film_poll_winning_suggestion ON film_poll (winning_suggestion_id)');

        // Revert film_poll_vote
        $this->addSql('ALTER TABLE film_poll_vote DROP FOREIGN KEY FK_D99AD08D567F5183');
        $this->addSql('DROP INDEX IDX_D99AD08D567F5183 ON film_poll_vote');
        $this->addSql('DROP INDEX unique_poll_user_film ON film_poll_vote');
        $this->addSql('ALTER TABLE film_poll_vote CHANGE film_id suggestion_id INT NOT NULL');
        $this->addSql('ALTER TABLE film_poll_vote ADD CONSTRAINT `FK_film_poll_vote_suggestion` FOREIGN KEY (suggestion_id) REFERENCES film_suggestion (id)');
        $this->addSql('CREATE INDEX IDX_film_poll_vote_suggestion ON film_poll_vote (suggestion_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_poll_user_suggestion ON film_poll_vote (poll_id, user_id, suggestion_id)');
    }
}
