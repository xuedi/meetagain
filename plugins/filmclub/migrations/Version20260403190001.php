<?php declare(strict_types=1);

namespace PluginFilmclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403190001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy datetime_immutable COMMENT annotations and align index names to Doctrine standard for filmclub tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vote CHANGE closes_at closes_at DATETIME NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE vote_ballot CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE vote_ballot RENAME INDEX idx_vote_ballot_vote TO IDX_D97B9BE772DCDAFC');
        $this->addSql('ALTER TABLE vote_ballot RENAME INDEX idx_vote_ballot_film TO IDX_D97B9BE7567F5183');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vote CHANGE closes_at closes_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vote_ballot CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE vote_ballot RENAME INDEX idx_d97b9be7567f5183 TO IDX_vote_ballot_film');
        $this->addSql('ALTER TABLE vote_ballot RENAME INDEX idx_d97b9be772dcdafc TO IDX_vote_ballot_vote');
    }
}
