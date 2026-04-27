<?php declare(strict_types=1);

namespace App\Enum;

enum LandingLayout: string
{
    case Single = 'single';
    case Pair = 'pair';
    case Trio = 'trio';
    case Grid = 'grid';
    case Compressed = 'compressed';
    case Accordion = 'accordion';
}
