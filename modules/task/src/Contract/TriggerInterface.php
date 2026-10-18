<?php declare(strict_types=1);

namespace Module\Task\Contract;

interface TriggerInterface
{
    public function runSoon(string $identifier): void;
}
