<?php declare(strict_types=1);

namespace App\Localization\Source;

use App\Entity\CmsTitle;
use App\Localization\LocalizedContentRow;
use Override;

final readonly class CmsTitleSource extends AbstractCmsLocalizedSource
{
    #[Override]
    public function getKey(): string
    {
        return 'cms_title';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'localized_content.source_cms_title';
    }

    #[Override]
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array
    {
        $rows = [];
        /** @var CmsTitle $title */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $title) {
            $page = $title->getCms();
            $rows[] = new LocalizedContentRow(
                sourceKey: $this->getKey(),
                ownerId: (int) $page?->getId(),
                locale: (string) $title->getLanguage(),
                ownerLabel: $this->pageLabel($page, $keepLocales),
                preview: $this->preview($title->getTitle()),
            );
        }

        return $rows;
    }

    #[Override]
    protected function getEntityClass(): string
    {
        return CmsTitle::class;
    }

    #[Override]
    protected function getLocaleField(): string
    {
        return 'language';
    }

    #[Override]
    protected function getOwnerField(): string
    {
        return 'cms';
    }
}
