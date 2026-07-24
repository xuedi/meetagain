<?php declare(strict_types=1);

namespace App\Enum;

enum FieldResolution: string
{
    case Applied = 'applied';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'review.resolution_applied',
            self::Denied => 'review.resolution_denied',
        };
    }
}
