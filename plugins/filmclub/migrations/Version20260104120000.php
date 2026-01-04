<?php

declare(strict_types=1);

namespace PluginFilmclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260104120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vote and vote_ballot tables for film voting feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vote (
            id INT AUTO_INCREMENT NOT NULL,
            event_id INT NOT NULL,
            closes_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            is_closed TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_by INT NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE vote_ballot (
            id INT AUTO_INCREMENT NOT NULL,
            vote_id INT NOT NULL,
            film_id INT NOT NULL,
            member_id INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_vote_ballot_vote (vote_id),
            INDEX IDX_vote_ballot_film (film_id),
            UNIQUE INDEX unique_member_vote (vote_id, member_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_vote_ballot_vote FOREIGN KEY (vote_id) REFERENCES vote (id) ON DELETE CASCADE,
            CONSTRAINT FK_vote_ballot_film FOREIGN KEY (film_id) REFERENCES film (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vote_ballot');
        $this->addSql('DROP TABLE vote');
    }
}
