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
            self::General => 'support.contact_type_general',
            self::Bug     => 'support.contact_type_bug',
            self::Feature => 'support.contact_type_feature',
            self::Legal   => 'support.contact_type_legal',
            self::Billing => 'support.contact_type_billing',
            self::Other   => 'support.contact_type_other',
        };
    }
}
