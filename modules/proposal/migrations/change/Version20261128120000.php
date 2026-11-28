<?php declare(strict_types=1);

namespace ModuleProposalMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Proposal module - a change carries the proposer\'s reason and a requested apply scope';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_proposal_change ADD reason LONGTEXT DEFAULT NULL, ADD requested_scope VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_proposal_change DROP reason, DROP requested_scope');
    }
}
