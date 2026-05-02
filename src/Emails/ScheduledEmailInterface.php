<?php declare(strict_types=1);

namespace App\Emails;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ScheduledEmailInterface extends EmailInterface
{
    /** @return DueContext[] */
    public function getDueContexts(DateTimeImmutable $now): array;

    /**
     * Same shape as getDueContexts but used by the admin guard-detail page. Skips dedup and
     * time-of-day gates so an operator can preview "what would the cron evaluate against these
     * recipients at the planned moment?" at any time.
     *
     * @return DueContext[]
     */
    public function getPreviewContexts(DateTimeImmutable $for): array;

    public function markContextSent(DueContext $context): void;

    /** @return ScheduledMailItem[] */
    public function getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
