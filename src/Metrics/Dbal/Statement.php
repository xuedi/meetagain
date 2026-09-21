<?php declare(strict_types=1);

namespace App\Metrics\Dbal;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Override;

final class Statement extends AbstractStatementMiddleware
{
    public function __construct(
        StatementInterface $statement,
        private readonly Stats $stats,
    ) {
        parent::__construct($statement);
    }

    #[Override]
    public function execute(): Result
    {
        $startedAt = (int) hrtime(true);
        try {
            return parent::execute();
        } finally {
            $this->stats->record($startedAt);
        }
    }
}
