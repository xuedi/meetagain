<?php declare(strict_types=1);

namespace Module\Task\Contract;

interface HealthInterface
{
    public function health(): Health;
}
