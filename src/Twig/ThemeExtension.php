<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ThemeExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_style', [ThemeRuntime::class, 'style'], ['is_safe' => ['html']]),
        ];
    }
}
