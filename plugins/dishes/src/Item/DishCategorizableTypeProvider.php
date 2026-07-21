<?php declare(strict_types=1);

namespace Plugin\Dishes\Item;

use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\TaxonomyConfig;
use Override;
use Plugin\Dishes\Service\ConfigService;
use Plugin\Dishes\Service\DishService;

/**
 * Registers the 'dish' item type as categorizable and taggable. Orthogonal to the event-attachable
 * DishItemTypeProvider: dishes implement both seams. getTaxonomy() returns the scope-resolved dishes
 * config's taxonomy, so core reads per-scope definitions without knowing the dishes Config class.
 */
final readonly class DishCategorizableTypeProvider implements CategorizableTypeProviderInterface
{
    public function __construct(
        private ConfigService $configService,
    ) {}

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

    #[Override]
    public function supportsCategories(): bool
    {
        return true;
    }

    #[Override]
    public function supportsTags(): bool
    {
        return true;
    }

    #[Override]
    public function getTaxonomy(): TaxonomyConfig
    {
        return $this->configService->getConfig()->getTaxonomy();
    }
}
