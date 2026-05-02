<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\User;
use App\Filter\Event\UserEventDigestFilterInterface;
use App\Repository\EventRepository;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class UserHasGroupEventsThisWeekRule implements EmailGuardRuleInterface
{
    /**
     * @param iterable<UserEventDigestFilterInterface> $digestFilters
     */
    public function __construct(
        private EventRepository $eventRepo,
        #[AutowireIterator(UserEventDigestFilterInterface::class)]
        private iterable $digestFilters = [],
    ) {}

    public function getName(): string
    {
        return 'user_has_group_events_this_week';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Database;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        $user = $context['user'] ?? null;
        if (!$user instanceof User) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'user' key, or it is not a User instance.",
                'user',
            );
        }
        $weekStart = $context['weekStart'] ?? null;
        $weekEnd = $context['weekEnd'] ?? null;
        if (!$weekStart instanceof DateTimeInterface || !$weekEnd instanceof DateTimeInterface) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing valid 'weekStart' and/or 'weekEnd' (expected DateTimeInterface).",
                'weekStart',
            );
        }

        $events = $this->eventRepo->findUpcomingEventsNotRsvpdByUser($weekStart, $weekEnd, $user);
        foreach ($this->digestFilters as $filter) {
            $events = $filter->filterForUser($events, $user);
            if ($events === []) {
                break;
            }
        }

        if ($events === []) {
            return EmailGuardResult::skip(
                $this->getName(),
                'No events this week match this user (filtered out by group/digest filters or already RSVP\'d).',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
