<?php declare(strict_types=1);

namespace Module\Email\Contract;

use DateTimeImmutable;

final readonly class SentEmail
{
    /**
     * @param array<string, mixed> $context the variables the message was rendered with, without the engine's own `_` keys
     * @param array<string, mixed> $layout the sending identity frozen when the message was queued
     */
    public function __construct(
        public int $id,
        public ?string $template,
        public string $sender,
        public string $recipient,
        public string $subject,
        public string $lang,
        public QueueStatus $status,
        public array $context,
        public array $layout,
        public ?string $renderedBody,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $maxSendBy,
        public ?DateTimeImmutable $dispatchedAt,
        public ?string $providerStatus,
        public ?string $errorMessage,
    ) {}
}
