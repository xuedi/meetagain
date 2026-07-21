<?php declare(strict_types=1);

namespace Plugin\Films\Item;

use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\TaxonomyConfig;
use Override;
use Plugin\Films\Service\ConfigService;
use Plugin\Films\Service\FilmService;

/**
 * Registers the 'film' item type as categorizable and taggable, reading its scope-resolved
 * definitions from the films taxonomy config. Orthogonal to the event-attachable FilmItemTypeProvider.
 */
final readonly class FilmCategorizableTypeProvider implements CategorizableTypeProviderInterface
{
    public function __construct(
        private ConfigService $configService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'films';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return FilmService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'films.item_label';
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
