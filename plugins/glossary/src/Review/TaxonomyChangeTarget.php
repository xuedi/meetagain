<?php declare(strict_types=1);

namespace Plugin\Glossary\Review;

use App\Item\Taxonomy\ChangeTarget;
use Override;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;

final class TaxonomyChangeTarget extends ChangeTarget
{
    #[Override]
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    #[Override]
    protected function getTypeKey(): string
    {
        return GlossaryCategorizableTypeProvider::ITEM_TYPE;
    }
}
