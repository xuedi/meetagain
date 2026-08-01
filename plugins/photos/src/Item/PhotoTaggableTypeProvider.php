<?php declare(strict_types=1);

namespace Plugin\Photos\Item;

use App\Item\Tag\TaggableTypeProviderInterface;
use Override;
use Plugin\Photos\Service\PhotoService;

final readonly class PhotoTaggableTypeProvider implements TaggableTypeProviderInterface
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'photos';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return PhotoService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'photos.item_label';
    }
}
