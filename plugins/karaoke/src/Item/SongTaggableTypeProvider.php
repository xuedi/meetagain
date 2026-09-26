<?php declare(strict_types=1);

namespace Plugin\Karaoke\Item;

use App\Item\Tag\TaggableTypeProviderInterface;
use Override;
use Plugin\Karaoke\Service\SongService;

final readonly class SongTaggableTypeProvider implements TaggableTypeProviderInterface
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'karaoke';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return SongService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'karaoke.item_label';
    }
}
