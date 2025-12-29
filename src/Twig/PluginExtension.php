<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\PluginService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PluginExtension extends AbstractExtension
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_active_plugins', $this->pluginService->getActiveList(...)),
        ];
    }
}
