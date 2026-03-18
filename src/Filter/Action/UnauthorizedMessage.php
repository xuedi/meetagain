<?php

declare(strict_types=1);

namespace App\Filter\Action;

use App\Enum\FlashMessageType;

readonly class UnauthorizedMessage
{
    public function __construct(
        public string $message,
        public FlashMessageType $type = FlashMessageType::Error,
    ) {}
}
