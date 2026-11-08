<?php declare(strict_types=1);

namespace ModuleProposalMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Proposal module - drop the change_proposal compatibility view';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS change_proposal');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE VIEW change_proposal AS SELECT * FROM mod_proposal_change');
    }
}
