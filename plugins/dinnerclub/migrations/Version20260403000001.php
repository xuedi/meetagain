<?php declare(strict_types=1);

namespace PluginDinnerclubMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename PluginDishesMigrations entries to PluginDinnerclubMigrations in doctrine_migration_versions';
    }

    public function up(Schema $schema): void
    {
        $oldVersions = [
            'PluginDishesMigrations\\Version20250811175826',
            'PluginDishesMigrations\\Version20250811192316',
            'PluginDishesMigrations\\Version20250811195331',
            'PluginDishesMigrations\\Version20250811200934',
            'PluginDishesMigrations\\Version20250811202038',
            'PluginDishesMigrations\\Version20250823184008',
            'PluginDishesMigrations\\Version20260112100001',
            'PluginDishesMigrations\\Version20260112100002',
            'PluginDishesMigrations\\Version20260402100001',
        ];

        foreach ($oldVersions as $old) {
            $new = str_replace('PluginDishesMigrations\\', 'PluginDinnerclubMigrations\\', $old);
            // If the new version already exists AND the old one is still present (mixed re-deploy state),
            // remove the old entry so the subsequent UPDATE does not cause a duplicate key violation.
            $this->addSql(
                'DELETE FROM doctrine_migration_versions WHERE version = :old AND EXISTS (SELECT 1 FROM (SELECT version FROM doctrine_migration_versions WHERE version = :new) AS t)',
                ['old' => $old, 'new' => $new]
            );
            $this->addSql(
                'UPDATE doctrine_migration_versions SET version = :new WHERE version = :old',
                ['new' => $new, 'old' => $old]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $newVersions = [
            'PluginDinnerclubMigrations\\Version20250811175826',
            'PluginDinnerclubMigrations\\Version20250811192316',
            'PluginDinnerclubMigrations\\Version20250811195331',
            'PluginDinnerclubMigrations\\Version20250811200934',
            'PluginDinnerclubMigrations\\Version20250811202038',
            'PluginDinnerclubMigrations\\Version20250823184008',
            'PluginDinnerclubMigrations\\Version20260112100001',
            'PluginDinnerclubMigrations\\Version20260112100002',
            'PluginDinnerclubMigrations\\Version20260402100001',
        ];

        foreach ($newVersions as $new) {
            $old = str_replace('PluginDinnerclubMigrations\\', 'PluginDishesMigrations\\', $new);
            $this->addSql(
                'UPDATE doctrine_migration_versions SET version = :old WHERE version = :new',
                ['old' => $old, 'new' => $new]
            );
        }
    }
}
