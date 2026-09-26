<?php declare(strict_types=1);

namespace Plugin\Karaoke\Enum;

enum LookupFailure: string
{
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';

    public function flashKey(): string
    {
        return match ($this) {
            self::RateLimited => 'karaoke_lookup.flash_rate_limited',
            self::Unavailable => 'karaoke_lookup.flash_unavailable',
        };
    }
}
