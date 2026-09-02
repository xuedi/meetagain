<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CirculationExtension extends AbstractExtension
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('circulation_enabled', [CirculationRuntime::class, 'isEnabled']),
            new TwigFunction('circulation_warm', [CirculationRuntime::class, 'warm']),
            new TwigFunction('circulation_badge', [CirculationRuntime::class, 'badge'], ['is_safe' => ['html']]),
            new TwigFunction('circulation_panel', [CirculationRuntime::class, 'panel'], ['is_safe' => ['html']]),
            new TwigFunction('circulation_dashboard_url', [CirculationRuntime::class, 'dashboardUrl']),
        ];
    }
}
