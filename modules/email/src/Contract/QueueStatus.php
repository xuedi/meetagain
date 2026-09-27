<?php declare(strict_types=1);

namespace Module\Email\Contract;

enum QueueStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Late = 'late';
}
