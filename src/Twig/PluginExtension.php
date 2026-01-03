<?php declare(strict_types=1);

namespace App\Twig;

use App\Plugin;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PluginExtension extends AbstractExtension
{
    public function __construct(
        #[AutowireIterator(Plugin::class)]
        private iterable $plugins,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        $links = [];
        foreach ($this->plugins as $plugin) {
            foreach ($plugin->getMenuLinks() as $link) {
                $links[] = $link;
            }
        }

        return [
            new TwigFunction('get_plugins_links', $links),
        ];
    }
}
