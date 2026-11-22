<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Link;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ProfileConfigRuntime implements RuntimeExtensionInterface
{
    private const array CORE_ENTRIES = [
        'app_profile_config' => ['profile_config.menu_settings', 10],
        'app_profile_config_password' => ['profile_config.menu_password', 20],
        'app_profile_blocked_users' => ['profile_config.menu_blocked', 30],
        'app_profile_config_export_page' => ['profile_config.menu_export', 100],
    ];

    public function __construct(
        private PluginRuntime $pluginRuntime,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
    ) {}

    /**
     * @return list<array{label: string, url: string, active: bool, danger: bool}>
     */
    public function menu(): array
    {
        $links = $this->pluginRuntime->getPluginProfileConfigLinks();
        foreach (self::CORE_ENTRIES as $route => [$label, $priority]) {
            $links[] = new Link($this->urlGenerator->generate($route), $label, $priority);
        }
        usort($links, static fn(Link $a, Link $b) => $a->getPriority() <=> $b->getPriority());
        $links[] = new Link($this->urlGenerator->generate('app_profile_config_delete_page'), 'profile_config.menu_delete');

        $active = $this->activeSlug(
            array_map(static fn(Link $link) => $link->getSlug(), $links),
            (string) $this->requestStack->getCurrentRequest()?->getPathInfo(),
        );
        $last = array_key_last($links);

        return array_map(
            static fn(int $index, Link $link) => [
                'label' => $link->getName(),
                'url' => $link->getSlug(),
                'active' => $link->getSlug() === $active,
                'danger' => $index === $last,
            ],
            array_keys($links),
            $links,
        );
    }

    /**
     * @param list<string> $slugs
     */
    public function activeSlug(array $slugs, string $path): ?string
    {
        $active = null;
        $longest = -1;
        foreach ($slugs as $slug) {
            $base = rtrim((string) parse_url($slug, PHP_URL_PATH), '/');
            if (($path === $base || str_starts_with($path, $base . '/')) && strlen($base) > $longest) {
                $active = $slug;
                $longest = strlen($base);
            }
        }

        return $active;
    }
}
