<?php

declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\DueContext;
use App\Emails\EmailQueueInterface;
use App\Emails\ScheduledEmailInterface;
use App\Emails\ScheduledMailItem;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class EventReminderEmail implements ScheduledEmailInterface
{
    public function __construct(
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private EventRepository $eventRepo,
        private EntityManagerInterface $em,
    ) {}

    public function getIdentifier(): string
    {
        return EmailType::EventReminder->value;
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Reminder: Go tournament afterparty is today',
            'context' => [
                'username' => 'John Doe',
                'eventTitle' => 'Go tournament afterparty',
                'eventLocation' => 'NightBar 64',
                'eventDate' => '2025-01-01',
                'eventTime' => '19:00',
                'eventId' => 1,
                'host' => 'https://localhost',
                'lang' => 'en',
            ],
        ];
    }

    public function guardCheck(array $context): bool
    {
        $user = $context['user'];

        if (!$user instanceof User) {
            return false;
        }
        if (!$user->isNotification()) {
            return false;
        }
        if (!$user->getNotificationSettings()->eventReminder) {
            return false;
        }

        return true;
    }

    public function send(array $context): void
    {
        /** @var User $user */
        $user = $context['user'];
        /** @var Event $event */
        $event = $context['event'];

        $language = $user->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $user->getName(),
            'eventTitle' => $event->getTitle($language),
            'eventLocation' => $event->getLocation()?->getName() ?? '',
            'eventDate' => $event->getStart()->format('Y-m-d'),
            'eventTime' => $event->getStart()->format('H:i'),
            'eventId' => $event->getId(),
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        $this->queue->enqueue($email, EmailType::EventReminder);
    }

    public function getDueContexts(DateTimeImmutable $now): array
    {
        $events = $this->eventRepo->findEventsNeedingReminder($now, $now->add(new DateInterval('PT5H')));

        $contexts = [];
        foreach ($events as $event) {
            $contexts[] = new DueContext(['event' => $event], $event->getRsvp()->toArray());
        }

        return $contexts;
    }

    public function markContextSent(DueContext $context): void
    {
        /** @var Event $event */
        $event = $context->data['event'];
        $event->setEventReminderSentAt(new DateTimeImmutable());
        $this->em->flush();
    }

    public function getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $events = $this->eventRepo->findEventsNeedingReminder(
            $from->add(new DateInterval('PT5H')),
            $to->add(new DateInterval('PT5H')),
        );

        $items = [];
        foreach ($events as $event) {
            $eligibleCount = 0;
            foreach ($event->getRsvp()->toArray() as $user) {
                if (!$this->guardCheck(['user' => $user, 'event' => $event])) {
                    continue;
                }

                $eligibleCount++;
            }

            $items[] = new ScheduledMailItem(
                mailType: EmailType::EventReminder->value,
                label: 'Event: ' . ($event->getTitle('en') ?: ($event->getTranslation()->first() ?: null)?->getTitle() ?? ''),
                expectedTime: DateTimeImmutable::createFromMutable($event->getStart())->sub(new DateInterval('PT5H')),
                expectedRecipients: $eligibleCount,
            );
        }

        return $items;
    }
}
