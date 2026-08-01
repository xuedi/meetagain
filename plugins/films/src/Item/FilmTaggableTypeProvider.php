<?php declare(strict_types=1);

namespace Plugin\Films\Item;

use App\Item\Tag\TaggableTypeProviderInterface;
use Override;
use Plugin\Films\Service\FilmService;

final readonly class FilmTaggableTypeProvider implements TaggableTypeProviderInterface
{
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
}
