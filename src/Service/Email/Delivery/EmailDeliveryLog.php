<?php declare(strict_types=1);

namespace App\Service\Email\Delivery;

use DateTimeImmutable;

final readonly class EmailDeliveryLog
{
    public function __construct(
        public string $messageId,
        public string $status,
        public string $recipientEmail,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $bounceType,
        public ?string $mailboxProvider,
        public array $rawData = [],
    ) {}

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isBounced(): bool
    {
        return $this->bounceType !== null;
    }
}
