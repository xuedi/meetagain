<?php declare(strict_types=1);

namespace Module\Email\Contract;

final readonly class SendOutcome
{
    public function __construct(
        public GuardResult $guard,
        public int $queued = 0,
    ) {}
}
