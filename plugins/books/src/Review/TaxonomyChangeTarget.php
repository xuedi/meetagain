<?php declare(strict_types=1);

namespace Plugin\Books\Review;

use App\Item\Taxonomy\ChangeTarget;
use Override;
use Plugin\Books\Service\BookService;

final class TaxonomyChangeTarget extends ChangeTarget
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'books';
    }

    #[Override]
    protected function getTypeKey(): string
    {
        return BookService::ITEM_TYPE;
    }
}
