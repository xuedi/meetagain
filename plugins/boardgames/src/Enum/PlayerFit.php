<?php declare(strict_types=1);

namespace Plugin\Boardgames\Enum;

enum PlayerFit: string
{
    case Fits = 'fits';
    case TooFew = 'too_few';
    case TooMany = 'too_many';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Fits => 'boardgames_tile.fit_fits',
            self::TooFew => 'boardgames_tile.fit_too_few',
            self::TooMany => 'boardgames_tile.fit_too_many',
            self::Unknown => 'boardgames_tile.fit_unknown',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Fits => 'is-success',
            self::TooFew => 'is-warning',
            self::TooMany => 'is-danger',
            self::Unknown => 'is-light',
        };
    }
}
