<?php declare(strict_types=1);

namespace App\Emails;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface EmailInterface
{
    public function getIdentifier(): string;

    /**
     * Translation key describing when this email is sent (e.g. 'Sunday 12:00', 'after registration').
     * Rendered in the admin templates list as a hint about the dispatch trigger.
     */
    public function getTriggerLabel(): string;

    /** @return array{subject: string, context: array<string, mixed>} */
    public function getDisplayMockData(): array;

    public function guardCheck(array $context): bool;

    public function send(array $context): void;

    /**
     * Cutoff for queue-level dispatch: if the email-queue cron picks up a row after this moment it will
     * skip the send and mark the row `late`. Return `null` to opt out (default behaviour, no cap).
     */
    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable;
}
