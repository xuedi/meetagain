<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\EventListItemTag;
use App\Entity\Link;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Service\Config\PluginService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

final class PluginRuntime implements RuntimeExtensionInterface
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

    public function getPluginsLinks(): array
    {
        /** @var list<Link> $links */
        $links = $this->collectFromPlugins(static fn(Plugin $p) => $p->getLinkCollection()->getNavLinks());

        usort($links, static fn(Link $a, Link $b) => $a->getPriority() <=> $b->getPriority());

        return $links;
    }

    public function getLeadingPluginLinks(): array
    {
        /** @var list<Link> $links */
        $links = $this->collectFromPlugins(static fn(Plugin $p) => $p->getLinkCollection()->getLeadingNavLinks());

        usort($links, static fn(Link $a, Link $b) => $a->getPriority() <=> $b->getPriority());

        return $links;
    }

    /**
     * @return list<string>
     */
    public function getPluginStylesheets(): array
    {
        return $this->collectFromPlugins(static fn(Plugin $plugin) => array_map(
            static fn(string $path) => 'plugins/' . $plugin->getPluginKey() . '/' . ltrim($path, '/'),
            $plugin->getStylesheets(),
        ));
    }

    /**
     * @return list<string>
     */
    public function getPluginJavascripts(): array
    {
        return $this->collectFromPlugins(static fn(Plugin $plugin) => array_map(
            static fn(string $path) => 'plugins/' . $plugin->getPluginKey() . '/' . ltrim($path, '/'),
            $plugin->getJavascripts(),
        ));
    }

    public function getPluginFooterAbout(): ?string
    {
        return $this->findFirstFromPlugins(static fn(Plugin $p) => $p->getFooterAbout());
    }

    public function getPluginFooterLinks(string $column): array
    {
        return $this->collectFromPlugins(static fn(Plugin $p) => $p->getLinkCollection()->getFooterLinks($column));
    }

    public function getPluginProfileDropdownLinks(): array
    {
        /** @var list<Link> $links */
        $links = $this->collectFromPlugins(static fn(Plugin $p) => $p->getLinkCollection()->getProfileDropdownLinks());

        usort($links, static fn(Link $a, Link $b) => $a->getPriority() <=> $b->getPriority());

        return $links;
    }

    /**
     * @return list<Link>
     */
    public function getPluginProfileConfigLinks(): array
    {
        /** @var list<Link> $links */
        $links = $this->collectFromPlugins(static fn(Plugin $p) => $p->getLinkCollection()->getProfileConfigLinks());

        usort($links, static fn(Link $a, Link $b) => $a->getPriority() <=> $b->getPriority());

        return $links;
    }

    public function getPluginNavbarPillsHtml(): string
    {
        /** @var list<string> $fragments */
        $fragments = $this->collectFromPlugins(static fn(Plugin $p) => $p->getLinkCollection()->getNavbarPillsHtml());

        return implode('', $fragments);
    }

    /**
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

        /** @var list<EventListItemTag> $tags */
        $tags = $this->collectFromPlugins(static function (Plugin $plugin) use ($eventId) {
            $validTags = [];
            foreach ($plugin->getEventListItemTags($eventId) as $tag) {
                $validTags[] = $tag;
            }
            return $validTags;
        });

        $this->tagCache[$eventId] = $tags;

        return $this->renderTags($tags);
    }

    /**
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
                        foreach ($result as $item) {
                            $results[] = $item;
                        }
                        continue;
                    }
                    $results[] = $result;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $results;
    }

    /**
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
