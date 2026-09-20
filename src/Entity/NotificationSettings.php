<?php declare(strict_types=1);

namespace App\Entity;

use InvalidArgumentException;
use JsonSerializable;

class NotificationSettings implements JsonSerializable
{
    public const array KEYS = [
        'announcements',
        'followingUpdates',
        'receivedMessage',
        'eventReminder',
        'upcomingEvents',
        'attendedEventUpdate',
    ];

    public bool $announcements;

    public bool $followingUpdates;

    public bool $receivedMessage;

    public bool $eventReminder;

    public bool $upcomingEvents;

    public bool $attendedEventUpdate;

    public function __construct(array $data)
    {
        $this->announcements = $data['announcements'] ?? true;
        $this->followingUpdates = $data['followingUpdates'] ?? false;
        $this->receivedMessage = $data['receivedMessage'] ?? true;
        $this->eventReminder = $data['eventReminder'] ?? true;
        $this->upcomingEvents = $data['upcomingEvents'] ?? true;
        $this->attendedEventUpdate = $data['attendedEventUpdate'] ?? true;
    }

    public static function fromJson(?array $notificationSettings): self
    {
        if ($notificationSettings === null) {
            return new self([]);
        }

        return new self($notificationSettings);
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

    public static function isKnownKey(string $type): bool
    {
        return in_array($type, self::KEYS, true);
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string>
     */
    public static function unknownKeys(array $keys): array
    {
        return array_values(array_filter($keys, static fn(string $key): bool => !self::isKnownKey($key)));
    }

    public function set(string $type, bool $value): self
    {
        match ($type) {
            'announcements' => $this->announcements = $value,
            'followingUpdates' => $this->followingUpdates = $value,
            'receivedMessage' => $this->receivedMessage = $value,
            'eventReminder' => $this->eventReminder = $value,
            'upcomingEvents' => $this->upcomingEvents = $value,
            'attendedEventUpdate' => $this->attendedEventUpdate = $value,
            default => throw new InvalidArgumentException(sprintf("Invalid type: '%s'", $type)),
        };

        return $this;
    }

    public function toggle(string $type): self
    {
        return $this->set($type, !$this->isActive($type));
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
            default => throw new InvalidArgumentException(sprintf("Invalid type: '%s'", $type)),
        };
    }
}
