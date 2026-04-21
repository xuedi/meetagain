<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\DueContext;
use App\Emails\EmailQueueInterface;
use App\Emails\ScheduledEmailInterface;
use App\Emails\ScheduledMailItem;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Filter\Event\UserEventDigestFilterInterface;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class UpcomingDigestEmail implements ScheduledEmailInterface
{
    private const string STATE_KEY_LAST_WEEK = 'upcoming_events_digest_last_week';

    /**
     * @param iterable<UserEventDigestFilterInterface> $digestFilters
     */
    public function __construct(
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private AppStateService $appStateService,
        #[AutowireIterator(UserEventDigestFilterInterface::class)]
        private iterable $digestFilters,
    ) {}

    public function getIdentifier(): string
    {
        return EmailType::UpcomingEvents->value;
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Upcoming events this week',
            'context' => [
                'username' => 'John Doe',
                'eventsHtml' => '<div style="margin-bottom:16px;padding:12px;border:1px solid #ddd;"><p><b>Go tournament afterparty</b></p><p>2025-01-01 19:00 - NightBar 64</p><p><a href="https://localhost/en/event/1">More Info</a> &nbsp; <a href="https://localhost/en/event/1#rsvp">I Want to Go</a></p></div>',
                'host' => 'https://localhost',
                'lang' => 'en',
            ],
        ];
    }

    public function guardCheck(array $context): bool
    {
        /** @var User $user */
        $user = $context['user'];

        return $user->getNotificationSettings()->upcomingEvents;
    }

    public function send(array $context): void
    {
        /** @var User $user */
        $user = $context['user'];
        $weekStart = $context['weekStart'];
        $weekEnd = $context['weekEnd'];

        $events = $this->eventRepo->findUpcomingEventsNotRsvpdByUser($weekStart, $weekEnd, $user);
        $events = $this->applyDigestFilters($events, $user);

        if ($events === []) {
            return;
        }

        $eventsHtml = $this->renderEventsHtml($events, $user);
        $language = $user->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $user->getName(),
            'eventsHtml' => $eventsHtml,
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        $this->queue->enqueue($this, $email, EmailType::UpcomingEvents, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return $now->add(new DateInterval('PT4H'));
    }

    public function getDueContexts(DateTimeImmutable $now): array
    {
        $dayOfWeek = (int) $now->format('w');
        $currentHour = (int) $now->format('H');

        if ($dayOfWeek !== 0 || $currentHour !== 12) {
            return [];
        }

        $weekKey = $now->modify('+1 day')->format('W');
        if ($this->appStateService->get(self::STATE_KEY_LAST_WEEK) === $weekKey) {
            return [];
        }

        $weekStart = $now->modify('+1 day');
        $weekEnd = $now->modify('+8 days');
        $allUsers = $this->userRepo->findAnnouncementSubscribers();

        return [new DueContext(
            ['week' => $weekKey, 'weekStart' => $weekStart, 'weekEnd' => $weekEnd],
            $allUsers,
        )];
    }

    public function markContextSent(DueContext $context): void
    {
        $this->appStateService->set(self::STATE_KEY_LAST_WEEK, $context->data['week']);
    }

    public function getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $nextSunday = $this->findNextSundayNoon($from, $to);
        if ($nextSunday === null) {
            return [];
        }

        $weekKey = $nextSunday->modify('+1 day')->format('W');
        if ($this->appStateService->get(self::STATE_KEY_LAST_WEEK) === $weekKey) {
            return [];
        }

        $weekStart = $nextSunday->modify('+1 day');
        $weekEnd = $nextSunday->modify('+8 days');
        $allUsers = $this->userRepo->findAnnouncementSubscribers();

        $eligibleCount = 0;
        foreach ($allUsers as $user) {
            if (!$user->getNotificationSettings()->upcomingEvents) {
                continue;
            }
            $events = $this->eventRepo->findUpcomingEventsNotRsvpdByUser($weekStart, $weekEnd, $user);
            $events = $this->applyDigestFilters($events, $user);
            if ($events !== []) {
                $eligibleCount++;
            }
        }

        return [new ScheduledMailItem(
            mailType: EmailType::UpcomingEvents->value,
            label: 'Weekly Digest',
            expectedTime: $nextSunday,
            expectedRecipients: $eligibleCount,
        )];
    }

    private function findNextSundayNoon(DateTimeImmutable $from, DateTimeImmutable $to): ?DateTimeImmutable
    {
        $candidate = $from->setTime(12, 0, 0);
        while ($candidate <= $to) {
            if ((int) $candidate->format('w') === 0) {
                return $candidate;
            }
            $candidate = $candidate->modify('+1 day');
        }

        return null;
    }

    /**
     * @param array<Event> $events
     * @return array<Event>
     */
    private function applyDigestFilters(array $events, User $user): array
    {
        foreach ($this->digestFilters as $filter) {
            $events = $filter->filterForUser($events, $user);
            if ($events === []) {
                return [];
            }
        }

        return $events;
    }

    /**
     * @param array<Event> $events
     */
    private function renderEventsHtml(array $events, User $user): string
    {
        $lang = $user->getLocale();
        $host = $this->config->getHost();

        $html = '';
        foreach ($events as $event) {
            $title = htmlspecialchars($event->getTitle($lang));
            $location = htmlspecialchars($event->getLocation()?->getName() ?? '');
            $date = $event->getStart()->format('Y-m-d H:i');
            $url = sprintf('%s/%s/event/%s', $host, $lang, $event->getId());

            $html .= sprintf(
                '<div style="margin-bottom:16px;padding:12px;border:1px solid #ddd;">' .
                '<p><b>%s</b></p><p>%s - %s</p>' .
                '<p><a href="%s">More Info</a> &nbsp; <a href="%s#rsvp">I Want to Go</a></p>' .
                '</div>',
                $title, $date, $location, $url, $url,
            );
        }

        return $html;
    }
}
