<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ConfigExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_date_format', [ConfigRuntime::class, 'getDateFormat']),
            new TwigFunction('get_date_format_flatpickr', [ConfigRuntime::class, 'getDateFormatFlatpickr']),
            new TwigFunction('get_footer_column_title', [ConfigRuntime::class, 'getFooterColumnTitle']),
            new TwigFunction('site_logo', [ConfigRuntime::class, 'siteLogo']),
            new TwigFunction('has_image_attributions', [ConfigRuntime::class, 'hasImageAttributions']),
        ];
    }
}
