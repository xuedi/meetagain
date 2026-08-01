<?php declare(strict_types=1);

namespace Plugin\Books\Item;

use App\Item\Tag\TaggableTypeProviderInterface;
use Override;
use Plugin\Books\Service\BookService;

final readonly class BookTaggableTypeProvider implements TaggableTypeProviderInterface
{
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
}
