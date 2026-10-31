<?php declare(strict_types=1);

namespace ModuleSuggestionMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suggestion module - the table takes the module prefix; rows and keys move with it, the generated index names follow the new table name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE suggestion TO mod_suggestion');
        $this->addSql('ALTER TABLE mod_suggestion RENAME INDEX idx_dd80f31bdab5a938 TO IDX_3D076C26DAB5A938');
        $this->addSql('ALTER TABLE mod_suggestion RENAME INDEX idx_dd80f31bfc6b21f1 TO IDX_3D076C26FC6B21F1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mod_suggestion RENAME INDEX IDX_3D076C26DAB5A938 TO idx_dd80f31bdab5a938');
        $this->addSql('ALTER TABLE mod_suggestion RENAME INDEX IDX_3D076C26FC6B21F1 TO idx_dd80f31bfc6b21f1');
        $this->addSql('RENAME TABLE mod_suggestion TO suggestion');
    }
}
