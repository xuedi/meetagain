<?php

declare(strict_types=1);

namespace App\Enum;

enum FlashMessageType: string
{
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
    case Info = 'info';
}
