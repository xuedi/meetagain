<?php declare(strict_types=1);

namespace App\Portability;

enum DataCategory: string
{
    case Profile = 'profile';
    case Attendance = 'attendance';
    case Interactions = 'interactions';
    case Collections = 'collections';
    case Uploads = 'uploads';
}
