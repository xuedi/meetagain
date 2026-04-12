<?php declare(strict_types=1);

namespace App\Service\Event;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Entity\Event;
use App\ValueObject\CronTaskResult;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\EmailService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;

readonly class RsvpNotificationService implements CronTaskInterface
{
    public function __construct(
        private EventRepository $eventRepo,
        private EmailService $emailService,
        private ConfigService $configService,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {}

    public function getIdentifier(): string
    {
        return 'rsvp-notifications';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            $currentHour = (int) $this->clock->now()->format('H');
            if ($currentHour < 7 || $currentHour >= 22) {
                $message = 'skipped: outside allowed hours (07:00-22:00)';
                $output->writeln('RSVP notifications ' . $message);

                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
            }

            $result = $this->processUpcomingEvents();
            $output->writeln('Send RSVP notifications: ' . $result);
            $this->logger->info('RSVP notifications processed', ['result' => $result]);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $result);
        } catch (\Throwable $e) {
            $output->writeln('RsvpNotificationService exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }

    public function processUpcomingEvents(): string
    {
        if (!$this->configService->isSendRsvpNotifications()) {
            return 'disabled';
        }

        $now = $this->clock->now();
        $end = $now->add(new DateInterval('PT48H'));

        $events = $this->eventRepo->findUpcomingEventsNeedingRsvpNotification($now, $end);
        $totalNotifications = 0;

        foreach ($events as $event) {
            $totalNotifications += $this->notifyFollowersForEvent($event);

            $event->setRsvpNotificationSentAt(new DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $totalNotifications . ' sent';
    }

    public function notifyFollowersForEvent(Event $event): int
    {
        if (!$this->configService->isSendRsvpNotifications()) {
            return 0;
        }

        $attendees = $event->getRsvp();
        if ($attendees->isEmpty()) {
            return 0;
        }

        /** @var array<int, array<User>> $recipientMap [recipientId => [attendees]] */
        $recipientMap = [];

        foreach ($attendees as $attendee) {
            if (!$attendee instanceof User) {
                continue;
            }

            foreach ($attendee->getFollowers() as $follower) {
                if (!$follower instanceof User) {
                    continue;
                }

                if (!$this->shouldNotify($follower, $attendee, $event)) {
                    continue;
                }

                if (!isset($recipientMap[$follower->getId()])) {
                    $recipientMap[$follower->getId()] = [
                        'recipient' => $follower,
                        'attendees' => [],
                    ];
                }
                $recipientMap[$follower->getId()]['attendees'][] = $attendee;
            }
        }

        $sentCount = 0;
        foreach ($recipientMap as $data) {
            /** @var User $recipient */
            $recipient = $data['recipient'];
            /** @var array<User> $followedAttendees */
            $followedAttendees = $data['attendees'];

            $this->emailService->prepareAggregatedRsvpNotification($recipient, $followedAttendees, $event);
            ++$sentCount;
        }

        return $sentCount;
    }

    private function shouldNotify(User $recipient, User $attendee, Event $event): bool
    {
        if (!$recipient->isNotification()) {
            return false;
        }

        if (!$recipient->getNotificationSettings()->followingUpdates) {
            return false;
        }

        if ($event->hasRsvp($recipient)) {
            return false;
        }

        return true;
    }
}
