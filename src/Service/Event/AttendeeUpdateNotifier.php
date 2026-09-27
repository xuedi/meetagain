<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Emails\Types\EventUpdateNotificationEmail;
use App\Entity\Event;
use App\Entity\User;
use DateTime;

readonly class AttendeeUpdateNotifier
{
    public function __construct(
        private EventUpdateNotificationEmail $email,
    ) {}

    /**
     * @return array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool}
     */
    public function snapshot(Event $event): array
    {
        return [
            'start' => $event->getStart()->getTimestamp(),
            'startFormatted' => $event->getStart()->format('Y-m-d H:i'),
            'locationId' => $event->getLocation()?->getId(),
            'locationName' => $event->getLocation()?->getName() ?? '',
            'canceled' => $event->isCanceled(),
        ];
    }

    /**
     * @param array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} $before
     * @param array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} $after
     */
    public function notify(Event $event, ?User $editor, array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        foreach ($this->recipients($event, $editor) as $recipient) {
            $this->email->send([
                'user' => $recipient,
                'event' => $event,
                'before' => $before,
                'after' => $after,
            ]);
        }
    }

    public function countNotifiable(Event $event, ?User $editor = null): int
    {
        return count($this->recipients($event, $editor));
    }

    /** @return list<User> */
    private function recipients(Event $event, ?User $editor): array
    {
        if ($event->getStart() <= new DateTime()) {
            return [];
        }

        $creatorId = $event->getUser()?->getId();
        $editorId = $editor?->getId();

        $recipients = [];
        foreach ($event->getRsvp() as $recipient) {
            if (!$recipient instanceof User) {
                continue;
            }
            if ($recipient->getId() === $creatorId || $recipient->getId() === $editorId) {
                continue;
            }
            if (!$this->email->guardCheck(['user' => $recipient, 'event' => $event])) {
                continue;
            }

            $recipients[] = $recipient;
        }

        return $recipients;
    }
}
