<?php declare(strict_types=1);

namespace App\Enum\CmsBlock;

enum MapWidth: string
{
    case Third = 'third';
    case Half = 'half';
    case TwoThirds = 'two_thirds';

    public function columnClass(): string
    {
        return match ($this) {
            self::Third => 'is-4',
            self::Half => 'is-6',
            self::TwoThirds => 'is-8',
        };
    }
}
