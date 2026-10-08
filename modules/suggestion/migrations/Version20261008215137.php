<?php declare(strict_types=1);

namespace ModuleSuggestionMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008215137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suggestion module - a suggestion remembers the scope it was made in, so it is reviewed and created there';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_suggestion ADD scope VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_suggestion DROP scope');
    }
}
