<?php declare(strict_types=1);

namespace App\Enum;

enum CommandTriggerType: string
{
    case Cron = 'cron';
    case Manual = 'manual';
}
