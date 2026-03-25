<?php declare(strict_types=1);

namespace App\Enum;

enum ContactType: string
{
    case General = 'general';
    case Bug = 'bug';
    case Feature = 'feature';
    case Legal = 'legal';
    case Billing = 'billing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General inquiry',
            self::Bug     => 'Report a bug',
            self::Feature => 'Feature request',
            self::Legal   => 'Legal message',
            self::Billing => 'Billing question',
            self::Other   => 'Other',
        };
    }
}
