<?php declare(strict_types=1);

namespace App\Anonymization;

use Doctrine\DBAL\Connection;

readonly class Scrubber
{
    public const string FILLER = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. ';
    public const string ADDRESS_DOMAIN = 'example.invalid';

    public function __construct(
        private Connection $connection,
    ) {}

    public function fill(string $table, string ...$columns): int
    {
        $count = 0;
        foreach ($columns as $column) {
            $quoted = $this->connection->quoteSingleIdentifier($column);
            $count += $this->connection->executeStatement(
                sprintf(
                    "UPDATE %s SET %2\$s = SUBSTRING(REPEAT(:filler, CEIL(CHAR_LENGTH(%2\$s) / :length) + 1), 1 + MOD(id, :length), CHAR_LENGTH(%2\$s)) WHERE %2\$s IS NOT NULL AND %2\$s <> ''",
                    $this->connection->quoteSingleIdentifier($table),
                    $quoted,
                ),
                ['filler' => self::FILLER, 'length' => strlen(self::FILLER)],
            );
        }

        return $count;
    }

    /**
     * @param array<string, string> $assignments column => SQL expression
     * @param array<string, mixed> $params
     */
    public function rewrite(string $table, array $assignments, string $where = '1 = 1', array $params = []): int
    {
        $set = [];
        foreach ($assignments as $column => $expression) {
            $set[] = sprintf('%s = %s', $this->connection->quoteSingleIdentifier($column), $expression);
        }

        return $this->connection->executeStatement(
            sprintf('UPDATE %s SET %s WHERE %s', $this->connection->quoteSingleIdentifier($table), implode(', ', $set), $where),
            $params,
        );
    }

    public function clear(string $table, string ...$columns): int
    {
        $count = 0;
        foreach ($columns as $column) {
            $quoted = $this->connection->quoteSingleIdentifier($column);
            $count += $this->connection->executeStatement(sprintf(
                'UPDATE %s SET %2$s = NULL WHERE %2$s IS NOT NULL',
                $this->connection->quoteSingleIdentifier($table),
                $quoted,
            ));
        }

        return $count;
    }

    public function empty(string $table): int
    {
        return $this->connection->executeStatement(sprintf('DELETE FROM %s', $this->connection->quoteSingleIdentifier($table)));
    }

    public function address(string $prefix, string $idColumn = 'id'): string
    {
        return sprintf("CONCAT('%s', %s, '@%s')", $prefix, $this->connection->quoteSingleIdentifier($idColumn), self::ADDRESS_DOMAIN);
    }
}
