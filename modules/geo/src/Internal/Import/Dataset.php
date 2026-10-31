<?php declare(strict_types=1);

namespace Module\Geo\Internal\Import;

enum Dataset: string
{
    case Countries = 'countries';
    case States = 'states';
    case Cities = 'cities';
    case Districts = 'districts';
    case Translations = 'translations';
}
