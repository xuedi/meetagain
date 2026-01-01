<?php declare(strict_types=1);

namespace App\Entity;

enum CommandTriggerType: string
{
    case Cron = 'cron';
    case Manual = 'manual';
}
