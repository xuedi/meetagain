<?php declare(strict_types=1);

namespace ModuleProposalMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Proposal module - change_proposal becomes mod_proposal_change, with a view under the old name for the previous releases';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE change_proposal TO mod_proposal_change');
        $this->addSql('ALTER TABLE mod_proposal_change RENAME INDEX idx_101f889cdab5a938 TO IDX_E7D7E9BBDAB5A938');
        $this->addSql('ALTER TABLE mod_proposal_change RENAME INDEX idx_101f889cfc6b21f1 TO IDX_E7D7E9BBFC6B21F1');
        $this->addSql('CREATE VIEW change_proposal AS SELECT * FROM mod_proposal_change');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW change_proposal');
        $this->addSql('ALTER TABLE mod_proposal_change RENAME INDEX IDX_E7D7E9BBDAB5A938 TO idx_101f889cdab5a938');
        $this->addSql('ALTER TABLE mod_proposal_change RENAME INDEX IDX_E7D7E9BBFC6B21F1 TO idx_101f889cfc6b21f1');
        $this->addSql('RENAME TABLE mod_proposal_change TO change_proposal');
    }
}
