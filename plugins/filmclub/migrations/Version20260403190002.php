<?php declare(strict_types=1);

namespace PluginFilmclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403190002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ON DELETE CASCADE from vote_ballot.film_id — film is a shared entity, deleting a film should be restricted not silently wipe vote history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vote_ballot DROP FOREIGN KEY `FK_vote_ballot_film`');
        $this->addSql('ALTER TABLE vote_ballot ADD CONSTRAINT FK_D97B9BE7567F5183 FOREIGN KEY (film_id) REFERENCES film (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vote_ballot DROP FOREIGN KEY FK_D97B9BE7567F5183');
        $this->addSql('ALTER TABLE vote_ballot ADD CONSTRAINT `FK_vote_ballot_film` FOREIGN KEY (film_id) REFERENCES film (id) ON DELETE CASCADE');
    }
}
