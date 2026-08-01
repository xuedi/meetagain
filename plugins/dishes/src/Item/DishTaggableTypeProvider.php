<?php declare(strict_types=1);

namespace Plugin\Dishes\Item;

use App\Item\Tag\TaggableTypeProviderInterface;
use Override;
use Plugin\Dishes\Service\DishService;

final readonly class DishTaggableTypeProvider implements TaggableTypeProviderInterface
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'dishes';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return DishService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'dishes.item_label';
    }
}
