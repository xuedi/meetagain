<?php declare(strict_types=1);

namespace App\Metrics\Gauge;

use App\Metrics\GaugeInterface;
use App\Metrics\Point;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DatabaseGauge implements GaugeInterface
{
    private const array STATUS_VARIABLES = [
        'Threads_connected',
        'Threads_running',
        'Slow_queries',
        'Questions',
        'Innodb_buffer_pool_reads',
        'Innodb_buffer_pool_read_requests',
    ];
    private const int LARGEST_TABLES = 15;

    public function __construct(
        private Connection $connection,
    ) {}

    public function collect(): iterable
    {
        $status = $this->connection->fetchAllKeyValue('SHOW GLOBAL STATUS WHERE Variable_name IN (?)', [self::STATUS_VARIABLES], [ArrayParameterType::STRING]);
        $fields = [];
        foreach ($status as $name => $value) {
            $fields[strtolower((string) $name)] = (int) $value;
        }
        if ($fields !== []) {
            yield new Point('database', $fields);
        }

        $tables = $this->connection->fetchAllAssociative('SELECT TABLE_NAME AS name, TABLE_ROWS AS row_count, DATA_LENGTH + INDEX_LENGTH AS bytes
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY bytes DESC
             LIMIT ' . self::LARGEST_TABLES);
        foreach ($tables as $table) {
            yield new Point(
                'database_table',
                [
                    'rows' => (int) $table['row_count'],
                    'size_mb' => round((int) $table['bytes'] / 1_048_576, 2),
                ],
                ['table' => (string) $table['name']],
            );
        }
    }
}
