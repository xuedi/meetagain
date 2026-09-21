<?php declare(strict_types=1);

namespace App\Metrics\Dbal;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Override;

final class Connection extends AbstractConnectionMiddleware
{
    public function __construct(
        ConnectionInterface $connection,
        private readonly Stats $stats,
    ) {
        parent::__construct($connection);
    }

    #[Override]
    public function prepare(string $sql): StatementInterface
    {
        return new Statement(parent::prepare($sql), $this->stats);
    }

    #[Override]
    public function query(string $sql): Result
    {
        $startedAt = (int) hrtime(true);
        try {
            return parent::query($sql);
        } finally {
            $this->stats->record($startedAt);
        }
    }

    #[Override]
    public function exec(string $sql): int|string
    {
        $startedAt = (int) hrtime(true);
        try {
            return parent::exec($sql);
        } finally {
            $this->stats->record($startedAt);
        }
    }
}
