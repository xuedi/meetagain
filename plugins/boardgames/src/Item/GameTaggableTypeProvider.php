<?php declare(strict_types=1);

namespace Plugin\Boardgames\Item;

use App\Item\Tag\TaggableTypeProviderInterface;
use Override;
use Plugin\Boardgames\Service\GameService;

final readonly class GameTaggableTypeProvider implements TaggableTypeProviderInterface
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'boardgames';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return GameService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'boardgames.item_label';
    }
}
