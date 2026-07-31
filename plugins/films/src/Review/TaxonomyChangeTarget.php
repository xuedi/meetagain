<?php declare(strict_types=1);

namespace Plugin\Films\Review;

use App\Item\Taxonomy\ChangeTarget;
use Override;
use Plugin\Films\Service\FilmService;

final class TaxonomyChangeTarget extends ChangeTarget
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'films';
    }

    #[Override]
    protected function getTypeKey(): string
    {
        return FilmService::ITEM_TYPE;
    }
}
