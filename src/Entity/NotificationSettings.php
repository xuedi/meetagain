<?php declare(strict_types=1);

namespace App\Entity;

use Exception;
use JsonSerializable;

class NotificationSettings implements JsonSerializable
{
    public bool $announcements;

    public bool $followingUpdates;

    public bool $receivedMessage;

    public bool $eventReminder;

    public bool $upcomingEvents;

    public bool $attendedEventUpdate;

    public static function fromJson(?array $notificationSettings): self
    {
        if ($notificationSettings === null) {
            return new self([]);
        }

        return new self($notificationSettings);
    }

    public function __construct(array $data)
    {
        $this->announcements = $data['announcements'] ?? true;
        $this->followingUpdates = $data['followingUpdates'] ?? false;
        $this->receivedMessage = $data['receivedMessage'] ?? true;
        $this->eventReminder = $data['eventReminder'] ?? true;
        $this->upcomingEvents = $data['upcomingEvents'] ?? true;
        $this->attendedEventUpdate = $data['attendedEventUpdate'] ?? true;
    }

    public function jsonSerialize(): array
    {
        return [
            'announcements' => $this->announcements,
            'followingUpdates' => $this->followingUpdates,
            'receivedMessage' => $this->receivedMessage,
            'eventReminder' => $this->eventReminder,
            'upcomingEvents' => $this->upcomingEvents,
            'attendedEventUpdate' => $this->attendedEventUpdate,
        ];
    }

    public function getList(): array
    {
        return [
            [
                'key' => 'announcements',
                'value' => $this->announcements,
                'label' => 'profile_config.toggle_announcements',
            ],
            [
                'key' => 'followingUpdates',
                'value' => $this->followingUpdates,
                'label' => 'profile_config.toggle_following_updates',
            ],
            [
                'key' => 'receivedMessage',
                'value' => $this->receivedMessage,
                'label' => 'profile_config.toggle_received_message',
            ],
            [
                'key' => 'eventReminder',
                'value' => $this->eventReminder,
                'label' => 'profile_config.toggle_event_reminder',
            ],
            [
                'key' => 'upcomingEvents',
                'value' => $this->upcomingEvents,
                'label' => 'profile_config.toggle_upcoming_events',
            ],
            [
                'key' => 'attendedEventUpdate',
                'value' => $this->attendedEventUpdate,
                'label' => 'profile_config.toggle_event_update',
            ],
        ];
    }

    public function toggle(string $type): self
    {
        switch ($type) {
            case 'announcements':
                $this->announcements = !$this->announcements;
                break;
            case 'followingUpdates':
                $this->followingUpdates = !$this->followingUpdates;
                break;
            case 'receivedMessage':
                $this->receivedMessage = !$this->receivedMessage;
                break;
            case 'eventReminder':
                $this->eventReminder = !$this->eventReminder;
                break;
            case 'upcomingEvents':
                $this->upcomingEvents = !$this->upcomingEvents;
                break;
            case 'attendedEventUpdate':
                $this->attendedEventUpdate = !$this->attendedEventUpdate;
                break;
            default:
                throw new Exception(sprintf("Invalid type: '%s'", $type));
        }

        return $this;
    }

    public function isActive(string $type): bool
    {
        return match ($type) {
            'announcements' => $this->announcements,
            'followingUpdates' => $this->followingUpdates,
            'receivedMessage' => $this->receivedMessage,
            'eventReminder' => $this->eventReminder,
            'upcomingEvents' => $this->upcomingEvents,
            'attendedEventUpdate' => $this->attendedEventUpdate,
            default => throw new Exception(sprintf("Invalid type: '%s'", $type)),
        };
    }
}
