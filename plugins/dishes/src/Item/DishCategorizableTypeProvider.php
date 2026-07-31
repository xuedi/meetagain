<?php declare(strict_types=1);

namespace Plugin\Dishes\Item;

use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\Config;
use Override;
use Plugin\Dishes\Service\ConfigService;
use Plugin\Dishes\Service\DishService;
use Plugin\Dishes\ValueObject\Config as DishesConfig;

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
    public function getSettingsKey(): string
    {
        return 'dishes';
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
    public function getTaxonomy(): Config
    {
        return $this->configService->getConfig()->getTaxonomy();
    }

    #[Override]
    public function taxonomyOf(object $settings): Config
    {
        \assert($settings instanceof DishesConfig);

        return $settings->getTaxonomy();
    }
}
