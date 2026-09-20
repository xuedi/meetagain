<?php declare(strict_types=1);

namespace App\DataHotfix\Hotfixes;

use App\DataHotfix\DataHotfixInterface;
use Doctrine\DBAL\Connection;
use Override;

readonly class RemoveVotingPluginLeftovers implements DataHotfixInterface
{
    private const string PLUGIN_KEY = 'voting';
    private const string MIGRATIONS_TABLE = 'doctrine_migration_versions';
    private const string SETTINGS_TABLE = 'plugin_settings';

    public function __construct(
        private Connection $connection,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return '2026_09_06_remove_voting_plugin_leftovers';
    }

    #[Override]
    public function execute(): void
    {
        if ($this->tableExists(self::MIGRATIONS_TABLE)) {
            $this->connection->executeStatement(sprintf('DELETE FROM %s WHERE version LIKE ?', self::MIGRATIONS_TABLE), ['PluginVotingMigrations%']);
        }

        if ($this->tableExists(self::SETTINGS_TABLE)) {
            $this->connection->executeStatement(sprintf('DELETE FROM %s WHERE plugin_key = ?', self::SETTINGS_TABLE), [self::PLUGIN_KEY]);
        }
    }

    private function tableExists(string $table): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table])
        > 0;
    }
}
