<?php declare(strict_types=1);

namespace App\Service\Event;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Entity\Event;
use App\ValueObject\CronTaskResult;
use App\Entity\User;
use App\Filter\Event\UserEventDigestFilterInterface;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use App\Service\Email\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class UpcomingEventsDigestService implements CronTaskInterface
{
    private const string STATE_KEY_LAST_WEEK = 'upcoming_events_digest_last_week';

    /**
     * @param iterable<UserEventDigestFilterInterface> $digestFilters
     */
    public function __construct(
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private EmailService $emailService,
        private ConfigService $config,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        private AppStateService $appStateService,
        #[AutowireIterator(UserEventDigestFilterInterface::class)]
        private iterable $digestFilters,
    ) {}

    public function getIdentifier(): string
    {
        return 'upcoming-events-digest';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            if (!$this->config->isUpcomingDigestEnabled()) {
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'skipped: disabled');
            }

            $now = $this->clock->now();
            $dayOfWeek = (int) $now->format('w');
            $currentHour = (int) $now->format('H');

            if ($dayOfWeek !== 0 || $currentHour !== 12) {
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'skipped: not Sunday at noon');
            }

            $result = $this->processDigest();
            $output->writeln('Upcoming events digest: ' . $result);
            $this->logger->info('Upcoming events digest processed', ['result' => $result]);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $result);
        } catch (\Throwable $e) {
            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }

    public function processDigest(): string
    {
        if ($this->isWeekAlreadySent()) {
            return '0 digests sent (already sent this week)';
        }

        $now = $this->clock->now();
        $weekStart = $now->modify('+1 day');
        $weekEnd = $now->modify('+8 days');
        $sentCount = 0;

        foreach ($this->userRepo->findAnnouncementSubscribers() as $user) {
            if (!$user->getNotificationSettings()->upcomingEvents) {
                continue;
            }

            $events = $this->eventRepo->findUpcomingEventsNotRsvpdByUser($weekStart, $weekEnd, $user);
            $events = $this->applyDigestFilters($events, $user);

            if ($events === []) {
                continue;
            }

            $eventsHtml = $this->renderEventsHtml($events, $user);
            $this->emailService->prepareUpcomingEvents($user, $eventsHtml);
            ++$sentCount;
        }

        if ($sentCount > 0) {
            $this->markWeekAsSent();
        }

        return $sentCount . ' digests sent';
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

    private function getTargetWeekNumber(): string
    {
        return $this->clock->now()->modify('+1 day')->format('W');
    }

    private function isWeekAlreadySent(): bool
    {
        return $this->appStateService->get(self::STATE_KEY_LAST_WEEK) === $this->getTargetWeekNumber();
    }

    private function markWeekAsSent(): void
    {
        $this->appStateService->set(self::STATE_KEY_LAST_WEEK, $this->getTargetWeekNumber());
    }
}
