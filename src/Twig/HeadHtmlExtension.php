<?php declare(strict_types=1);

namespace App\Twig;

use App\Publisher\HeadHtml\Registry;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class HeadHtmlExtension extends AbstractExtension
{
    public function __construct(
        private readonly Registry $registry,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_head_html', $this->registry->render(...), ['is_safe' => ['html']]),
        ];
    }
}
