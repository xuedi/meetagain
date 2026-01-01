<?php declare(strict_types=1);

namespace App\Entity;

enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
}
