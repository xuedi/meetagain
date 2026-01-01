<?php declare(strict_types=1);

namespace App\Entity;

enum CommandExecutionStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
    case Timeout = 'timeout';
}
