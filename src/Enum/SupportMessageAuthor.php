<?php declare(strict_types=1);

namespace App\Enum;

enum SupportMessageAuthor: string
{
    case Requester = 'requester';
    case Admin = 'admin';
}
