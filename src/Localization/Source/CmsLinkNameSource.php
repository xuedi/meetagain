<?php declare(strict_types=1);

namespace App\Localization\Source;

use App\Entity\CmsLinkName;
use App\Localization\LocalizedContentRow;
use Override;

final readonly class CmsLinkNameSource extends AbstractCmsLocalizedSource
{
    #[Override]
    public function getKey(): string
    {
        return 'cms_link_name';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'localized_content.source_cms_link_name';
    }

    #[Override]
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array
    {
        $rows = [];
        /** @var CmsLinkName $linkName */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $linkName) {
            $page = $linkName->getCms();
            $rows[] = new LocalizedContentRow(
                sourceKey: $this->getKey(),
                ownerId: (int) $page?->getId(),
                locale: (string) $linkName->getLanguage(),
                ownerLabel: $this->pageLabel($page, $keepLocales),
                preview: $this->preview($linkName->getName()),
            );
        }

        return $rows;
    }

    #[Override]
    protected function getEntityClass(): string
    {
        return CmsLinkName::class;
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
