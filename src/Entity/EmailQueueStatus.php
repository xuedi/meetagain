<?php declare(strict_types=1);

namespace App\Entity;

enum EmailQueueStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
