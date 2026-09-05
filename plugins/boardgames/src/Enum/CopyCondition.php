<?php declare(strict_types=1);

namespace Plugin\Boardgames\Enum;

enum CopyCondition: string
{
    case Mint = 'mint';
    case Good = 'good';
    case Played = 'played';
    case Worn = 'worn';
    case Incomplete = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::Mint => 'boardgames_shelf.condition_mint',
            self::Good => 'boardgames_shelf.condition_good',
            self::Played => 'boardgames_shelf.condition_played',
            self::Worn => 'boardgames_shelf.condition_worn',
            self::Incomplete => 'boardgames_shelf.condition_incomplete',
        };
    }
}
