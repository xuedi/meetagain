<?php declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

enum ImageReportReason: int
{
    case Privacy = 1;
    case Copyright = 2;
    case Inappropriate = 3;
    case Irrelevant = 4;

    public function label(): string
    {
        return 'report.reason_' . strtolower($this->name);
    }

    public static function getChoices(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$translator->trans('report.reason_' . strtolower($case->name))] = $case;
        }

        return $choices;
    }

    public static function getTranslatedList(TranslatorInterface $translator): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->value] = $translator->trans('report.reason_' . strtolower($case->name));
        }

        return $choices;
    }
}
