<?php declare(strict_types=1);

namespace App\Service\Notification\User;

enum Level: string
{
    case Action = 'action';
    case Warning = 'warning';
    case Info = 'info';

    public function color(): string
    {
        return match ($this) {
            self::Action => 'danger',
            self::Warning => 'warning',
            self::Info => 'info',
        };
    }

    public function urgency(): int
    {
        return match ($this) {
            self::Action => 3,
            self::Warning => 2,
            self::Info => 1,
        };
    }
}
