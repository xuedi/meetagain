<?php

declare(strict_types=1);

namespace App\Authorization\Action;

readonly class UnauthorizedMessage
{
    public function __construct(
        public string $message,
        public string $type = 'error',
    ) {}
}
