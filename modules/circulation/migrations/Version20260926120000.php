<?php declare(strict_types=1);

namespace ModuleCirculationMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Circulation module - the four tables take the module prefix; rows and keys move with them, the generated index names follow the new table names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'RENAME TABLE circulation_copy TO mod_circulation_copy, circulation_request TO mod_circulation_request, '
            . 'circulation_handover TO mod_circulation_handover, circulation_ledger TO mod_circulation_ledger',
        );
        $this->addSql('ALTER TABLE mod_circulation_copy RENAME INDEX idx_53123b6a7cdfee88 TO IDX_61CCC1FA7CDFEE88');
        $this->addSql('ALTER TABLE mod_circulation_handover RENAME INDEX idx_ee3699012130303a TO IDX_3C4937B22130303A');
        $this->addSql('ALTER TABLE mod_circulation_handover RENAME INDEX idx_ee36990129f6ee60 TO IDX_3C4937B229F6EE60');
        $this->addSql('ALTER TABLE mod_circulation_handover RENAME INDEX idx_ee369901427eb8a5 TO IDX_3C4937B2427EB8A5');
        $this->addSql('ALTER TABLE mod_circulation_handover RENAME INDEX idx_ee369901187b2d12 TO IDX_3C4937B2187B2D12');
        $this->addSql('ALTER TABLE mod_circulation_request RENAME INDEX idx_7fe8bd08828db255 TO IDX_DC3F32E828DB255');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_circulation_copy RENAME INDEX IDX_61CCC1FA7CDFEE88 TO idx_53123b6a7cdfee88');
        $this->addSql('ALTER TABLE mod_circulation_handover RENAME INDEX IDX_3C4937B22130303A TO idx_ee3699012130303a');
        $this->addSql('ALTER TABLE mod_circulation_handover RENAME INDEX IDX_3C4937B229F6EE60 TO idx_ee36990129f6ee60');
        $this->addSql('ALTER TABLE mod_circulation_handover RENAME INDEX IDX_3C4937B2427EB8A5 TO idx_ee369901427eb8a5');
        $this->addSql('ALTER TABLE mod_circulation_handover RENAME INDEX IDX_3C4937B2187B2D12 TO idx_ee369901187b2d12');
        $this->addSql('ALTER TABLE mod_circulation_request RENAME INDEX IDX_DC3F32E828DB255 TO idx_7fe8bd08828db255');
        $this->addSql(
            'RENAME TABLE mod_circulation_copy TO circulation_copy, mod_circulation_request TO circulation_request, '
            . 'mod_circulation_handover TO circulation_handover, mod_circulation_ledger TO circulation_ledger',
        );
    }
}
