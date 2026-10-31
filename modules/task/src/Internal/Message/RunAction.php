<?php declare(strict_types=1);

namespace Module\Task\Internal\Message;

final readonly class RunAction
{
    public function __construct(
        public int $requestId,
    ) {}
}
