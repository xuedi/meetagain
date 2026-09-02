<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class HeadHtmlExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_head_html', [HeadHtmlRuntime::class, 'render'], ['is_safe' => ['html']]),
        ];
    }
}
