<?php

declare(strict_types=1);

namespace PluginVotingMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Voting 1.0 - item-keyed polls, ballot options and approval votes (generalized from filmclub polls)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE poll (
            id INT AUTO_INCREMENT NOT NULL,
            event_id INT NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            end_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            duration_days INT NOT NULL,
            closed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            status INT NOT NULL,
            winning_item_id INT DEFAULT NULL,
            tied_item_ids JSON DEFAULT NULL,
            INDEX IDX_84BCFA4571F7E88B (event_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE poll_option (
            id INT AUTO_INCREMENT NOT NULL,
            poll_id INT NOT NULL,
            item_id INT NOT NULL,
            INDEX IDX_B68343EB3C947C0F (poll_id),
            UNIQUE INDEX uniq_poll_option_poll_item (poll_id, item_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE vote (
            id INT AUTO_INCREMENT NOT NULL,
            poll_id INT NOT NULL,
            user_id INT NOT NULL,
            item_id INT NOT NULL,
            voted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_5A1085643C947C0F (poll_id),
            UNIQUE INDEX uniq_vote_poll_user_item (poll_id, user_id, item_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE poll ADD CONSTRAINT FK_84BCFA4571F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poll_option ADD CONSTRAINT FK_B68343EB3C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A1085643C947C0F FOREIGN KEY (poll_id) REFERENCES poll (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poll_option DROP FOREIGN KEY FK_B68343EB3C947C0F');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A1085643C947C0F');
        $this->addSql('ALTER TABLE poll DROP FOREIGN KEY FK_84BCFA4571F7E88B');
        $this->addSql('DROP TABLE vote');
        $this->addSql('DROP TABLE poll_option');
        $this->addSql('DROP TABLE poll');
    }
}
