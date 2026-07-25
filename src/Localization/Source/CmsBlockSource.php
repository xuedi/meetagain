<?php declare(strict_types=1);

namespace App\Localization\Source;

use App\Entity\CmsBlock;
use App\Enum\CmsBlock\CmsBlockType;
use App\Localization\LocalizedContentRow;
use App\Service\Cms\CmsService;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class CmsBlockSource extends AbstractCmsLocalizedSource
{
    public function __construct(
        EntityManagerInterface $em,
        private CmsService $cmsService,
    ) {
        parent::__construct($em);
    }

    #[Override]
    public function getKey(): string
    {
        return 'cms_block';
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'localized_content.source_cms_block';
    }

    #[Override]
    public function findOutsideLocales(array $ownerIds, array $keepLocales): array
    {
        $rows = [];
        /** @var CmsBlock $block */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $block) {
            $page = $block->getPage();
            $rows[] = new LocalizedContentRow(
                sourceKey: $this->getKey(),
                ownerId: (int) $page?->getId(),
                locale: (string) $block->getLanguage(),
                ownerLabel: $this->pageLabel($page, $keepLocales),
                preview: $this->preview($this->blockText($block)),
            );
        }

        return $rows;
    }

    /**
     * The rendered inner body is cached per page, so a deleted block stays visible until every
     * touched page is invalidated.
     */
    #[Override]
    public function deleteOutsideLocales(array $ownerIds, array $keepLocales): int
    {
        $pageIds = [];
        /** @var CmsBlock $block */
        foreach ($this->fetchEntities($ownerIds, $keepLocales) as $block) {
            $pageId = $block->getPage()?->getId();
            if ($pageId !== null) {
                $pageIds[$pageId] = true;
            }
        }

        $deleted = parent::deleteOutsideLocales($ownerIds, $keepLocales);

        foreach (array_keys($pageIds) as $pageId) {
            $this->cmsService->invalidatePage($pageId);
        }

        return $deleted;
    }

    #[Override]
    protected function getEntityClass(): string
    {
        return CmsBlock::class;
    }

    #[Override]
    protected function getLocaleField(): string
    {
        return 'language';
    }

    #[Override]
    protected function getOwnerField(): string
    {
        return 'page';
    }

    private function blockText(CmsBlock $block): string
    {
        $json = $block->getJson();
        $candidates = [$json['title'] ?? null, $json['content'] ?? null, $json['headline'] ?? null];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        $type = $block->getType();

        return $type instanceof CmsBlockType ? $type->name : '';
    }
}
