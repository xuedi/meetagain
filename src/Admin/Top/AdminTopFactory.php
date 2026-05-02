<?php declare(strict_types=1);

namespace App\Admin\Top;

use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AdminTopFactory
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public function infoText(string $key, array $params = []): AdminTopInfoText
    {
        return new AdminTopInfoText($this->translator->trans($key, $params));
    }

    public function infoHtml(string $trustedHtml): AdminTopInfoHtml
    {
        return new AdminTopInfoHtml($trustedHtml);
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    public function actionButton(
        string $labelKey,
        string $route,
        array $routeParams = [],
        ?string $icon = null,
        ?string $variant = null,
    ): AdminTopActionButton {
        return new AdminTopActionButton(
            label: $this->translator->trans($labelKey),
            target: $this->router->generate($route, $routeParams),
            icon: $icon,
            variant: $variant,
        );
    }
}
