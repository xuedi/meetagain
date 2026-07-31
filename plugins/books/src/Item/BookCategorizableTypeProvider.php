<?php declare(strict_types=1);

namespace Plugin\Books\Item;

use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\Config;
use Override;
use Plugin\Books\Service\BookService;
use Plugin\Books\Service\ConfigService;
use Plugin\Books\ValueObject\Config as BooksConfig;

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
    public function getSettingsKey(): string
    {
        return 'books';
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
    public function getTaxonomy(): Config
    {
        return $this->configService->getConfig()->getTaxonomy();
    }

    #[Override]
    public function taxonomyOf(object $settings): Config
    {
        \assert($settings instanceof BooksConfig);

        return $settings->getTaxonomy();
    }
}
