<?php declare(strict_types=1);

namespace ModuleSuggestionMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Proposal module - mod_suggestion becomes mod_proposal_suggestion, with a view under the old name for the previous releases';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE mod_suggestion TO mod_proposal_suggestion');
        $this->addSql('ALTER TABLE mod_proposal_suggestion RENAME INDEX idx_3d076c26dab5a938 TO IDX_DD998E4BDAB5A938');
        $this->addSql('ALTER TABLE mod_proposal_suggestion RENAME INDEX idx_3d076c26fc6b21f1 TO IDX_DD998E4BFC6B21F1');
        $this->addSql('CREATE VIEW mod_suggestion AS SELECT * FROM mod_proposal_suggestion');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW mod_suggestion');
        $this->addSql('ALTER TABLE mod_proposal_suggestion RENAME INDEX IDX_DD998E4BDAB5A938 TO idx_3d076c26dab5a938');
        $this->addSql('ALTER TABLE mod_proposal_suggestion RENAME INDEX IDX_DD998E4BFC6B21F1 TO idx_3d076c26fc6b21f1');
        $this->addSql('RENAME TABLE mod_proposal_suggestion TO mod_suggestion');
    }
}
