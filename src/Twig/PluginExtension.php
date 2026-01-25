<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\AdminSection;
use App\Plugin;
use App\Service\PluginService;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PluginExtension extends AbstractExtension
{
    public function __construct(
        #[AutowireIterator(Plugin::class)]
        private readonly iterable $plugins,
        private readonly PluginService $pluginService,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_plugins_links', $this->getPluginsLinks(...)),
            new TwigFunction('get_plugins_admin_system_links', $this->getPluginsAdminSystemLinks(...)),
        ];
    }

    public function getPluginsLinks(): array
    {
        $enabledPlugins = $this->pluginService->getActiveList();
        $links = [];
        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }
            try {
                foreach ($plugin->getMenuLinks() as $link) {
                    $links[] = $link;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $links;
    }

    /**
     * @return list<AdminSection>
     */
    public function getPluginsAdminSystemLinks(): array
    {
        $enabledPlugins = $this->pluginService->getActiveList();
        $sections = [];
        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }
            try {
                $adminSection = $plugin->getAdminSystemLinks();
                if ($adminSection !== null) {
                    $sections[] = $adminSection;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $sections;
    }
}
