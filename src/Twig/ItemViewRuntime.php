<?php declare(strict_types=1);

namespace App\Twig;

use App\Enum\ItemViewType;
use App\Item\ListCellRegistry;
use App\Service\Item\ViewResolver;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ItemViewRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ViewResolver $viewResolver,
        private ListCellRegistry $registry,
    ) {}

    public function itemViewMode(string $itemType, ?string $default = null): ItemViewType
    {
        return $this->viewResolver->get($itemType, ItemViewType::tryFrom((string) $default) ?? ItemViewType::List);
    }

    /** @return list<ItemViewType> */
    public function itemViewTypes(): array
    {
        return ItemViewType::switchable();
    }

    public function itemListCell(string $itemType, int $itemId, ?string $mode = null): string
    {
        $provider = $this->registry->providerFor($itemType);
        if ($provider === null) {
            return '';
        }

        return $provider->renderListCell($itemId, ItemViewType::tryFrom((string) $mode)) ?? '';
    }
}
