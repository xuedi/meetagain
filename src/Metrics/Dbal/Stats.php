<?php declare(strict_types=1);

namespace App\Metrics\Dbal;

use Symfony\Contracts\Service\ResetInterface;

final class Stats implements ResetInterface
{
    private int $queries = 0;
    private int $nanoseconds = 0;

    public function record(int $startedAt): void
    {
        $this->queries++;
        $this->nanoseconds += (int) hrtime(true) - $startedAt;
    }

    public function getQueries(): int
    {
        return $this->queries;
    }

    public function getMilliseconds(): float
    {
        return round($this->nanoseconds / 1_000_000, 1);
    }

    public function reset(): void
    {
        $this->queries = 0;
        $this->nanoseconds = 0;
    }
}
