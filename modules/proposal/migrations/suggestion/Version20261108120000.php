<?php declare(strict_types=1);

namespace ModuleSuggestionMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Proposal module - drop the mod_suggestion compatibility view';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS mod_suggestion');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE VIEW mod_suggestion AS SELECT * FROM mod_proposal_suggestion');
    }
}
