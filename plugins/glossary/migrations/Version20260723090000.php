<?php declare(strict_types=1);

namespace PluginGlossaryMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The per-entry suggestion JSON blob is replaced by the core change_proposal table (universal
 * review tool); category assignments moved to the shared item taxonomy tables in the item
 * refactor, but the old column was never dropped. Both columns go together.
 */
final class Version20260723090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the legacy suggestion JSON column and the orphaned category column from plg_glossary_glossary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_glossary_glossary DROP suggestion, DROP category');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plg_glossary_glossary ADD suggestion JSON DEFAULT NULL, ADD category INT DEFAULT NULL');
    }
}
