<?php declare(strict_types=1);

namespace Plugin\Boardgames\Enum;

enum ExternalSource: string
{
    case Bgg = 'bgg';
    case Wikidata = 'wikidata';
    case Manual = 'manual';
}
