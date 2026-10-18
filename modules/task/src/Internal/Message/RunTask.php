<?php declare(strict_types=1);

namespace Module\Task\Internal\Message;

final readonly class RunTask
{
    public function __construct(
        public string $identifier,
    ) {}
}
