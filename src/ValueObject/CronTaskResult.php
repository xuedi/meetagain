<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\CronTaskStatus;

final readonly class CronTaskResult
{
    public function __construct(
        public string $identifier,
        public CronTaskStatus $status,
        public string $message,
    ) {}
}
