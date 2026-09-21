<?php declare(strict_types=1);

namespace App\Metrics\Dbal;

use App\Metrics\Recorder;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

#[AsMiddleware]
final readonly class Middleware implements MiddlewareInterface
{
    public function __construct(
        private Recorder $recorder,
        private Stats $stats,
    ) {}

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return $this->recorder->isEnabled() ? new Driver($driver, $this->stats) : $driver;
    }
}
