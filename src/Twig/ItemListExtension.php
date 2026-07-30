<?php declare(strict_types=1);

namespace App\Twig;

use App\Item\ListRegistry;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ItemListExtension extends AbstractExtension
{
    public const string BODY_TEMPLATE = '_components/item/list_body.html.twig';

    public function __construct(
        private readonly ListRegistry $registry,
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_list_body', $this->listBody(...), ['is_safe' => ['html']]),
            new TwigFunction('item_list_markup', $this->listMarkup(...), ['is_safe' => ['html']]),
            new TwigFunction('item_list_url', $this->listUrl(...)),
            new TwigFunction('item_list_count', $this->listCount(...)),
        ];
    }

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
