<?php declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

enum EventInterval: int
{
    case Daily = 1;
    case Weekly = 2;
    case BiMonthly = 3;
    case Monthly = 4;
    case Yearly = 5;

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'admin_event.interval_daily',
            self::Weekly => 'admin_event.interval_weekly',
            self::BiMonthly => 'admin_event.interval_bimonthly',
            self::Monthly => 'admin_event.interval_monthly',
            self::Yearly => 'admin_event.interval_yearly',
        };
    }

    public static function getChoices(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$translator->trans($case->label())] = $case;
        }

        return $choices;
    }
}
