<?php declare(strict_types=1);

namespace App\Admin\Tabs;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AdminTabsFactory
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param array<string, mixed> $routeParams
     */
    public function tab(
        string $labelKey,
        string $route,
        array $routeParams = [],
        ?string $icon = null,
        bool $isActive = false,
    ): AdminTab {
        return new AdminTab(
            label: $this->translator->trans($labelKey),
            target: $this->router->generate($route, $routeParams),
            icon: $icon,
            isActive: $isActive,
        );
    }
}
