<?php declare(strict_types=1);

namespace App\Enum;

enum EventCanonicalRootType: string
{
    case Root = 'root';
    case Detached = 'detached';
}
