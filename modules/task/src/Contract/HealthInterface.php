<?php declare(strict_types=1);

namespace Module\Task\Contract;

interface HealthInterface
{
    public function health(): Health;

    /** Deletes every failed message. Returns the number deleted. */
    public function discardFailed(): int;
}
