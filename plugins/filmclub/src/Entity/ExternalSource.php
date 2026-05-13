<?php declare(strict_types=1);

namespace Plugin\Filmclub\Entity;

enum ExternalSource: string
{
    case Tmdb = 'tmdb';
    case Omdb = 'omdb';
    case Manual = 'manual';
}
