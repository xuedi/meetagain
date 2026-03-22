<?php declare(strict_types=1);

namespace App\Enum;

enum ImageSupport
{
    case None;
    case Optional;
    case Required;
}
