<?php declare(strict_types=1);

namespace App\Service\Frontpage;

use App\Enum\LandingLayout;

final class ThinPickerLayoutResolver
{
    public function resolve(int $languageCount): LandingLayout
    {
        return match (true) {
            $languageCount <= 1 => LandingLayout::Single,
            $languageCount === 2 => LandingLayout::Pair,
            $languageCount === 3 => LandingLayout::Trio,
            $languageCount <= 6 => LandingLayout::Grid,
            $languageCount <= 9 => LandingLayout::Compressed,
            default => LandingLayout::Accordion,
        };
    }
}
