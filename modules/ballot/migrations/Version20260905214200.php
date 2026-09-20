<?php declare(strict_types=1);

namespace ModuleBallotMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905214200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ballot module 1.0 - deferred decisions, their candidates and their votes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mod_ballot (
            id INT AUTO_INCREMENT NOT NULL,
            purpose VARCHAR(191) NOT NULL,
            subject_type VARCHAR(64) DEFAULT NULL,
            subject_id INT DEFAULT NULL,
            status VARCHAR(16) NOT NULL,
            tally_mode VARCHAR(16) NOT NULL,
            settlement_mode VARCHAR(16) NOT NULL,
            deadline DATETIME NOT NULL,
            opened_by_user_id INT NOT NULL,
            winning_key VARCHAR(191) DEFAULT NULL,
            tied_keys JSON NOT NULL,
            settled_by_user_id INT DEFAULT NULL,
            settled_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_ballot_purpose (purpose),
            INDEX idx_ballot_status_deadline (status, deadline),
            INDEX idx_ballot_subject (subject_type, subject_id),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE mod_ballot_option (
            id INT AUTO_INCREMENT NOT NULL,
            ballot_id INT NOT NULL,
            option_key VARCHAR(191) NOT NULL,
            label VARCHAR(255) NOT NULL,
            position INT NOT NULL,
            INDEX IDX_7F96F6D6DDC23F6C (ballot_id),
            UNIQUE INDEX uniq_ballot_option_key (ballot_id, option_key),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE mod_ballot_vote (
            id INT AUTO_INCREMENT NOT NULL,
            ballot_id INT NOT NULL,
            user_id INT NOT NULL,
            option_key VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_14DB8C28DDC23F6C (ballot_id),
            INDEX IDX_14DB8C28A76ED395 (user_id),
            INDEX idx_ballot_vote_voter (ballot_id, user_id),
            UNIQUE INDEX uniq_ballot_vote_choice (ballot_id, user_id, option_key),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE mod_ballot_option ADD CONSTRAINT FK_7F96F6D6DDC23F6C FOREIGN KEY (ballot_id) REFERENCES mod_ballot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mod_ballot_vote ADD CONSTRAINT FK_14DB8C28DDC23F6C FOREIGN KEY (ballot_id) REFERENCES mod_ballot (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mod_ballot_vote ADD CONSTRAINT FK_14DB8C28A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_ballot_option DROP FOREIGN KEY FK_7F96F6D6DDC23F6C');
        $this->addSql('ALTER TABLE mod_ballot_vote DROP FOREIGN KEY FK_14DB8C28DDC23F6C');
        $this->addSql('ALTER TABLE mod_ballot_vote DROP FOREIGN KEY FK_14DB8C28A76ED395');
        $this->addSql('DROP TABLE mod_ballot_vote');
        $this->addSql('DROP TABLE mod_ballot_option');
        $this->addSql('DROP TABLE mod_ballot');
    }
}
