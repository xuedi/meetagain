<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AltLocaleExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('required_alt_locales', [AltLocaleRuntime::class, 'getRequiredAltLocales']),
        ];
    }
}
