<?php declare(strict_types=1);

namespace App\Emails;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ScheduledEmailInterface extends EmailInterface
{
    /** @return DueContext[] */
    public function getDueContexts(DateTimeImmutable $now): array;

    public function markContextSent(DueContext $context): void;

    /** @return ScheduledMailItem[] */
    public function getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
