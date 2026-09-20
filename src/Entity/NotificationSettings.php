<?php declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
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

    public const array PUSH_CATEGORIES = [
        'event-changes',
        'reminders',
        'messages',
        'announcements',
    ];

    public const string DEFAULT_TIME_ZONE = 'Europe/Berlin';
    public const string DEFAULT_QUIET_START = '22:00';
    public const string DEFAULT_QUIET_END = '07:00';

    public bool $announcements;

    public bool $followingUpdates;

    public bool $receivedMessage;

    public bool $eventReminder;

    public bool $upcomingEvents;

    public bool $attendedEventUpdate;

    /** @var array<string, bool> */
    public array $push;

    public bool $quietHoursEnabled;

    public string $quietHoursStart;

    public string $quietHoursEnd;

    public string $quietHoursTimeZone;

    public bool $quietHoursAllowUrgent;

    public function __construct(array $data)
    {
        $this->announcements = $data['announcements'] ?? true;
        $this->followingUpdates = $data['followingUpdates'] ?? false;
        $this->receivedMessage = $data['receivedMessage'] ?? true;
        $this->eventReminder = $data['eventReminder'] ?? true;
        $this->upcomingEvents = $data['upcomingEvents'] ?? true;
        $this->attendedEventUpdate = $data['attendedEventUpdate'] ?? true;

        $push = is_array($data['push'] ?? null) ? $data['push'] : [];
        $this->push = [];
        foreach (self::PUSH_CATEGORIES as $category) {
            $this->push[$category] = (bool) ($push[$category] ?? false);
        }

        $quiet = is_array($data['quietHours'] ?? null) ? $data['quietHours'] : [];
        $this->quietHoursEnabled = (bool) ($quiet['enabled'] ?? true);
        $this->quietHoursStart = (string) ($quiet['start'] ?? self::DEFAULT_QUIET_START);
        $this->quietHoursEnd = (string) ($quiet['end'] ?? self::DEFAULT_QUIET_END);
        $this->quietHoursTimeZone = (string) ($quiet['timeZone'] ?? self::DEFAULT_TIME_ZONE);
        $this->quietHoursAllowUrgent = (bool) ($quiet['allowUrgent'] ?? false);
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
            'push' => $this->push,
            'quietHours' => [
                'enabled' => $this->quietHoursEnabled,
                'start' => $this->quietHoursStart,
                'end' => $this->quietHoursEnd,
                'timeZone' => $this->quietHoursTimeZone,
                'allowUrgent' => $this->quietHoursAllowUrgent,
            ],
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

    public static function isKnownPushCategory(string $category): bool
    {
        return in_array($category, self::PUSH_CATEGORIES, true);
    }

    public static function isValidTimeOfDay(string $value): bool
    {
        return preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $value) === 1;
    }

    public static function isValidTimeZone(string $value): bool
    {
        try {
            new DateTimeZone($value);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function isPushEnabled(string $category): bool
    {
        if (!self::isKnownPushCategory($category)) {
            throw new InvalidArgumentException(sprintf("Invalid push category: '%s'", $category));
        }

        return $this->push[$category];
    }

    public function setPush(string $category, bool $value): self
    {
        if (!self::isKnownPushCategory($category)) {
            throw new InvalidArgumentException(sprintf("Invalid push category: '%s'", $category));
        }
        $this->push[$category] = $value;

        return $this;
    }

    public function setQuietHours(bool $enabled, string $start, string $end, string $timeZone, bool $allowUrgent): self
    {
        if (!self::isValidTimeOfDay($start) || !self::isValidTimeOfDay($end)) {
            throw new InvalidArgumentException('Quiet hours must be HH:MM.');
        }
        if (!self::isValidTimeZone($timeZone)) {
            throw new InvalidArgumentException(sprintf("Invalid time zone: '%s'", $timeZone));
        }

        $this->quietHoursEnabled = $enabled;
        $this->quietHoursStart = $start;
        $this->quietHoursEnd = $end;
        $this->quietHoursTimeZone = $timeZone;
        $this->quietHoursAllowUrgent = $allowUrgent;

        return $this;
    }

    public function isWithinQuietHours(DateTimeImmutable $moment): bool
    {
        if (!$this->quietHoursEnabled || $this->quietHoursStart === $this->quietHoursEnd) {
            return false;
        }

        $local = $moment->setTimezone(new DateTimeZone($this->quietHoursTimeZone))->format('H:i');
        if ($this->quietHoursStart < $this->quietHoursEnd) {
            return $local >= $this->quietHoursStart && $local < $this->quietHoursEnd;
        }

        return $local >= $this->quietHoursStart || $local < $this->quietHoursEnd;
    }

    public function quietHoursEndAfter(DateTimeImmutable $moment): DateTimeImmutable
    {
        $zone = new DateTimeZone($this->quietHoursTimeZone);
        $local = $moment->setTimezone($zone);
        $end = $local->modify($this->quietHoursEnd);
        if ($end <= $local) {
            $end = $end->modify('+1 day');
        }

        return $end->setTimezone($moment->getTimezone());
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
