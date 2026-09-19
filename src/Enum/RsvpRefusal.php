<?php declare(strict_types=1);

namespace App\Enum;

enum RsvpRefusal: string
{
    case Inaccessible = 'inaccessible';
    case Canceled = 'canceled';
    case Started = 'started';
    case NotAllowed = 'not_allowed';
    case NotGoing = 'not_going';
    case GuestCountInvalid = 'guest_count_invalid';

    public function flashKey(): string
    {
        return match ($this) {
            self::Canceled => 'events.flash_rsvp_canceled',
            self::Started => 'events.flash_rsvp_past',
            self::Inaccessible, self::NotAllowed => 'events.flash_group_only',
            self::NotGoing => 'events.flash_rsvp_guests_requires_rsvp',
            self::GuestCountInvalid => 'events.flash_rsvp_guests_limit',
        };
    }

    public function errorCode(): string
    {
        return match ($this) {
            self::NotGoing => 'not_rsvpd',
            self::Inaccessible, self::NotAllowed => 'not_allowed',
            default => 'event_' . $this->value,
        };
    }

    public function flashLevel(): string
    {
        return match ($this) {
            self::Canceled, self::Started => 'error',
            default => 'warning',
        };
    }
}
