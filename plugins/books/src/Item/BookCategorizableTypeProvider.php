<?php declare(strict_types=1);

namespace Plugin\Books\Item;

use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\TaxonomyConfig;
use Override;
use Plugin\Books\Service\ConfigService;
use Plugin\Books\Service\BookService;

/**
 * Registers the 'book' item type as categorizable and taggable, reading its scope-resolved
 * definitions from the books config. Orthogonal to the event-attachable BookItemTypeProvider.
 */
final readonly class BookCategorizableTypeProvider implements CategorizableTypeProviderInterface
{
    public function __construct(
        private ConfigService $configService,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'books';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return BookService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'books.item_label';
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
