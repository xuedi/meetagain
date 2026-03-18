<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\AdminSection;
use App\Entity\EventListItemTag;
use App\Entity\WarmCacheType;
use App\Plugin;
use App\Service\Config\PluginService;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PluginExtension extends AbstractExtension
{
    /**
     * @var array<int, list<EventListItemTag>>
     */
    private array $tagCache = [];

    public function __construct(
        #[AutowireIterator(Plugin::class)]
        private readonly iterable $plugins,
        private readonly PluginService $pluginService,
        private readonly Environment $twig,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_plugins_links', $this->getPluginsLinks(...)),
            new TwigFunction('get_plugins_admin_system_links', $this->getPluginsAdminSystemLinks(...)),
            new TwigFunction('get_plugin_stylesheets', $this->getPluginStylesheets(...)),
            new TwigFunction('get_plugin_javascripts', $this->getPluginJavascripts(...)),
            new TwigFunction('get_plugin_footer_about', $this->getPluginFooterAbout(...)),
            new TwigFunction('get_member_page_top', $this->getMemberPageTop(...), ['is_safe' => ['html']]),
            new TwigFunction('event_list_item_tags', $this->getEventListItemTags(...), ['is_safe' => ['html']]),
            new TwigFunction('warm_event_list_item_tags', $this->warmEventListItemTags(...)),
            new TwigFunction('is_plugin_enabled', $this->isPluginEnabled(...)),
        ];
    }

    public function getPluginsLinks(): array
    {
        $links = $this->collectFromPlugins(fn(Plugin $p) => $p->getMenuLinks());

        usort($links, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

        return $links;
    }

    public function isPluginEnabled(string $pluginKey): bool
    {
        return in_array($pluginKey, $this->pluginService->getActiveList(), true);
    }

    /**
     * @return list<AdminSection>
     */
    public function getPluginsAdminSystemLinks(): array
    {
        return $this->collectFromPlugins(fn(Plugin $p) => $p->getAdminSystemLinks());
    }

    /**
     * @return list<string>
     */
    public function getPluginStylesheets(): array
    {
        return $this->collectFromPlugins(function (Plugin $plugin) {
            $paths = [];
            foreach ($plugin->getStylesheets() as $path) {
                $paths[] = '/plugins/' . $plugin->getPluginKey() . '/' . ltrim($path, '/');
            }
            return $paths;
        });
    }

    /**
     * @return list<string>
     */
    public function getPluginJavascripts(): array
    {
        return $this->collectFromPlugins(function (Plugin $plugin) {
            $paths = [];
            foreach ($plugin->getJavascripts() as $path) {
                $paths[] = '/plugins/' . $plugin->getPluginKey() . '/' . ltrim($path, '/');
            }
            return $paths;
        });
    }

    /**
     * Returns rendered HTML for footer "about" section from first plugin that provides it.
     */
    public function getPluginFooterAbout(): ?string
    {
        return $this->findFirstFromPlugins(fn(Plugin $p) => $p->getFooterAbout());
    }

    /**
     * Returns rendered HTML for member page top section from first plugin that provides it.
     */
    public function getMemberPageTop(): ?string
    {
        return $this->findFirstFromPlugins(fn(Plugin $p) => $p->getMemberPageTop());
    }

    /**
     * Pre-warms per-request caches for all visible event IDs before the list render loop.
     *
     * @param array<int> $eventIds
     */
    public function warmEventListItemTags(array $eventIds): void
    {
        $enabledPlugins = $this->pluginService->getActiveList();

        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }

            try {
                $plugin->warmCache(WarmCacheType::EventListItemTags, $eventIds);
            } catch (Throwable) {
                continue;
            }
        }
    }

    public function getEventListItemTags(int $eventId): string
    {
        if (isset($this->tagCache[$eventId])) {
            return $this->renderTags($this->tagCache[$eventId]);
        }

        $tags = $this->collectFromPlugins(function (Plugin $plugin) use ($eventId) {
            $validTags = [];
            foreach ($plugin->getEventListItemTags($eventId) as $tag) {
                if ($tag instanceof EventListItemTag) {
                    $validTags[] = $tag;
                }
            }
            return $validTags;
        });

        $this->tagCache[$eventId] = $tags;

        return $this->renderTags($tags);
    }

    /**
     * Collects results from all enabled plugins using the provided callback.
     *
     * @template T
     * @param callable(Plugin): (T|list<T>|null) $callback
     * @return list<T>
     */
    private function collectFromPlugins(callable $callback): array
    {
        $enabledPlugins = $this->pluginService->getActiveList();
        $results = [];

        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }

            try {
                $result = $callback($plugin);
                if ($result !== null) {
                    if (is_array($result)) {
                        array_push($results, ...$result);
                    } else {
                        $results[] = $result;
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $results;
    }

    /**
     * Returns first non-null result from enabled plugins.
     *
     * @template T
     * @param callable(Plugin): ?T $callback
     * @return ?T
     */
    private function findFirstFromPlugins(callable $callback): mixed
    {
        $enabledPlugins = $this->pluginService->getActiveList();

        foreach ($this->plugins as $plugin) {
            if (!in_array($plugin->getPluginKey(), $enabledPlugins, true)) {
                continue;
            }

            try {
                $result = $callback($plugin);
                if ($result !== null) {
                    return $result;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param list<EventListItemTag> $tags
     */
    private function renderTags(array $tags): string
    {
        if ($tags === []) {
            return '';
        }

        return $this->twig->render('_components/event_list_item_tags.html.twig', [
            'tags' => $tags,
        ]);
    }
}
