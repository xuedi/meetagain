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
            new TwigFunction('get_plugin_stylesheets', $this->getPluginStylesheets(...)),
            new TwigFunction('get_plugin_javascripts', $this->getPluginJavascripts(...)),
            new TwigFunction('get_plugin_footer_about', $this->getPluginFooterAbout(...)),
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

    /**
     * @return list<string>
     */
    public function getPluginStylesheets(): array
    {
        $enabledPlugins = $this->pluginService->getActiveList();
        $stylesheets = [];
        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }
            try {
                foreach ($plugin->getStylesheets() as $path) {
                    $stylesheets[] = '/plugins/' . $plugin->getPluginKey() . '/' . ltrim($path, '/');
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $stylesheets;
    }

    /**
     * @return list<string>
     */
    public function getPluginJavascripts(): array
    {
        $enabledPlugins = $this->pluginService->getActiveList();
        $javascripts = [];
        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }
            try {
                foreach ($plugin->getJavascripts() as $path) {
                    $javascripts[] = '/plugins/' . $plugin->getPluginKey() . '/' . ltrim($path, '/');
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $javascripts;
    }

    /**
     * Returns rendered HTML for footer "about" section from first plugin that provides it.
     */
    public function getPluginFooterAbout(): ?string
    {
        $enabledPlugins = $this->pluginService->getActiveList();
        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }
            try {
                $footerAbout = $plugin->getFooterAbout();
                if ($footerAbout !== null) {
                    return $footerAbout;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
