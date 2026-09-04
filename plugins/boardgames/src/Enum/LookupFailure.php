<?php declare(strict_types=1);

namespace Plugin\Boardgames\Enum;

enum LookupFailure: string
{
    case Unauthorized = 'unauthorized';
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';

    public function flashKey(): string
    {
        return match ($this) {
            self::Unauthorized => 'boardgames_game.flash_token_rejected',
            self::RateLimited => 'boardgames_game.flash_rate_limited',
            self::Unavailable => 'boardgames_game.flash_lookup_error',
        };
    }
}
