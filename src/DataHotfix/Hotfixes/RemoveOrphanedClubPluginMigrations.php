<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use Doctrine\DBAL\Connection;
use Override;

/**
 * Deletes leftover doctrine_migration_versions rows for the bookclub, dinnerclub and filmclub
 * plugins, which were replaced by the books, dishes and films item plugins. Their migration
 * classes no longer exist, so Doctrine reports the rows as "previously executed migrations that
 * are not registered" on every deploy. Removing the orphaned rows silences that warning without
 * affecting any live migration namespace (none of which share these prefixes).
 */
readonly class RemoveOrphanedClubPluginMigrations implements DataHotfixInterface
{
    private const string MIGRATIONS_TABLE = 'doctrine_migration_versions';

    private const array REMOVED_NAMESPACES = [
        'PluginBookclubMigrations',
        'PluginDinnerclubMigrations',
        'PluginFilmclubMigrations',
    ];

    public function __construct(
        private Connection $connection,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_07_20_remove_orphaned_club_plugin_migrations';
    }

    #[Override]
    public function execute(): void
    {
        if (!$this->tableExists(self::MIGRATIONS_TABLE)) {
            return;
        }

        foreach (self::REMOVED_NAMESPACES as $namespace) {
            $this->connection->executeStatement(
                sprintf('DELETE FROM %s WHERE version LIKE ?', self::MIGRATIONS_TABLE),
                [$namespace . '%'],
            );
        }
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table])
        > 0;
    }
}
