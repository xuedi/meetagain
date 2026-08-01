<?php declare(strict_types=1);

namespace Plugin\Glossary\Item;

use App\Item\Tag\TaggableTypeProviderInterface;
use Override;

final readonly class GlossaryTaggableTypeProvider implements TaggableTypeProviderInterface
{
    public const string ITEM_TYPE = 'glossary';

    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    public function getTypeKey(): string
    {
        return self::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'glossary.menu_main';
    }
}
