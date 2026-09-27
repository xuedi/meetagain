<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class Extension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('circulation_enabled', [Runtime::class, 'isEnabled']),
            new TwigFunction('circulation_warm', [Runtime::class, 'warm']),
            new TwigFunction('circulation_badge', [Runtime::class, 'badge'], ['is_safe' => ['html']]),
            new TwigFunction('circulation_panel', [Runtime::class, 'panel'], ['is_safe' => ['html']]),
            new TwigFunction('circulation_dashboard_url', [Runtime::class, 'dashboardUrl']),
        ];
    }
}
