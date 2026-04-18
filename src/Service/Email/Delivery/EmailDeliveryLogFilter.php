<?php declare(strict_types=1);

namespace App\Service\Email\Delivery;

use DateTimeImmutable;

final readonly class EmailDeliveryLogFilter
{
    public function __construct(
        public ?string $messageId = null,
        public ?string $recipientEmail = null,
        public ?array $statuses = null,
        public ?DateTimeImmutable $since = null,
        public ?DateTimeImmutable $until = null,
        public int $offset = 0,
        public int $size = 50,
    ) {}
}
