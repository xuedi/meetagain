<?php declare(strict_types=1);

namespace App\Twig;

use App\Enum\ItemViewType;
use App\Item\ItemTypeRegistry;
use App\Service\Item\ItemViewResolver;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig bridge for the shared item-list component: the current per-type view mode, the
 * available modes, and each item's provider-rendered list cell.
 */
final class ItemViewExtension extends AbstractExtension
{
    public function __construct(
        private readonly ItemViewResolver $viewResolver,
        private readonly ItemTypeRegistry $registry,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('item_view_mode', $this->itemViewMode(...)),
            new TwigFunction('item_view_types', $this->itemViewTypes(...)),
            new TwigFunction('item_list_cell', $this->itemListCell(...), ['is_safe' => ['html']]),
        ];
    }

    public function itemViewMode(string $itemType): ItemViewType
    {
        return $this->viewResolver->get($itemType);
    }

    /** @return list<ItemViewType> */
    public function itemViewTypes(): array
    {
        return ItemViewType::cases();
    }

    public function itemListCell(string $itemType, int $itemId): string
    {
        $provider = $this->registry->providerFor($itemType);
        if ($provider === null) {
            return '';
        }

        return $provider->renderListCell($itemId) ?? '';
    }
}
