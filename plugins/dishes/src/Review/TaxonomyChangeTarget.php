<?php declare(strict_types=1);

namespace Plugin\Dishes\Review;

use App\Item\Taxonomy\ChangeTarget;
use Override;
use Plugin\Dishes\Service\DishService;

final class TaxonomyChangeTarget extends ChangeTarget
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'dishes';
    }

    #[Override]
    protected function getTypeKey(): string
    {
        return DishService::ITEM_TYPE;
    }
}
