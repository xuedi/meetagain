<?php declare(strict_types=1);

namespace App\Metrics\Dbal;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

final class Driver extends AbstractDriverMiddleware
{
    public function __construct(
        DriverInterface $driver,
        private readonly Stats $stats,
    ) {
        parent::__construct($driver);
    }

    #[Override]
    public function connect(#[SensitiveParameter] array $params): ConnectionInterface
    {
        return new Connection(parent::connect($params), $this->stats);
    }
}
