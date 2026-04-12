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

readonly class EventReminderService implements CronTaskInterface
{
    public function __construct(
        private EventRepository $eventRepo,
        private EmailService $emailService,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private ConfigService $config,
    ) {}

    public function getIdentifier(): string
    {
        return 'event-reminders';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            if (!$this->config->isEventRemindersEnabled()) {
                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'skipped: disabled');
            }

            $currentHour = (int) $this->clock->now()->format('H');
            if ($currentHour < 7 || $currentHour >= 22) {
                $message = 'skipped: outside allowed hours (07:00-22:00)';
                $output->writeln('Event reminders: ' . $message);

                return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
            }

            $result = $this->processReminders();
            $output->writeln('Event reminders: ' . $result);
            $this->logger->info('Event reminders processed', ['result' => $result]);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $result);
        } catch (\Throwable $e) {
            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }

    public function processReminders(): string
    {
        $now = $this->clock->now();
        $events = $this->eventRepo->findEventsNeedingReminder(
            $now->add(new DateInterval('PT4H')),
            $now->add(new DateInterval('PT6H')),
        );
        $totalSent = 0;

        foreach ($events as $event) {
            $totalSent += $this->sendRemindersForEvent($event);
            $event->setEventReminderSentAt(new DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $totalSent . ' reminders sent';
    }

    private function sendRemindersForEvent(Event $event): int
    {
        $sentCount = 0;
        foreach ($event->getRsvp() as $user) {
            if (!$user instanceof User) {
                continue;
            }
            if (!$user->isNotification()) {
                continue;
            }
            if (!$user->getNotificationSettings()->eventReminder) {
                continue;
            }

            $this->emailService->prepareEventReminder($user, $event);
            ++$sentCount;
        }

        return $sentCount;
    }
}
