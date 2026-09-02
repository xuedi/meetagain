<?php declare(strict_types=1);

namespace App\Twig;

use App\Item\ListRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ItemListRuntime implements RuntimeExtensionInterface
{
    public const string BODY_TEMPLATE = '_components/item/list_body.html.twig';

    public function __construct(
        private ListRegistry $registry,
        private Environment $twig,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function listBody(string $itemType): string
    {
        return '<div data-item-list-body>' . $this->twig->render(self::BODY_TEMPLATE, ['itemType' => $itemType]) . '</div>';
    }

    public function listMarkup(string $itemType): string
    {
        return $this->registry->providerFor($itemType)?->renderList() ?? '';
    }

    public function listUrl(string $itemType): string
    {
        $route = $this->registry->providerFor($itemType)?->getListRoute();

        return $route !== null
            ? $this->urlGenerator->generate($route)
            : $this->requestStack->getCurrentRequest()?->getPathInfo() ?? '';
    }

    public function listCount(string $itemType): int
    {
        return count($this->registry->providerFor($itemType)?->getItemIds() ?? []);
    }
}
