<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Emails\ScheduledEmailInterface;
use App\Emails\ScheduledMailItem;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class PlannedEmailService
{
    /**
     * @param iterable<ScheduledEmailInterface> $scheduledEmails
     */
    public function __construct(
        #[AutowireIterator(ScheduledEmailInterface::class)]
        private iterable $scheduledEmails,
    ) {}

    /**
     * @return ScheduledMailItem[]
     */
    public function getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $items = [];
        foreach ($this->scheduledEmails as $email) {
            foreach ($email->getPlannedItems($from, $to) as $item) {
                $items[] = $item;
            }
        }

        usort($items, static fn(ScheduledMailItem $a, ScheduledMailItem $b) => $a->expectedTime <=> $b->expectedTime);

        return $items;
    }
}
