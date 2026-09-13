<?php declare(strict_types=1);

namespace App\Portability\Section;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\CmsLinkName;
use App\Entity\CmsMenuLocation;
use App\Entity\CmsTitle;
use App\Entity\Image;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\ImageType;
use App\Enum\MenuLocation;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\SectionInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;

readonly class CmsPagesSection implements SectionInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return 'cms_pages';
    }

    #[Override]
    public function getOrder(): int
    {
        return 50;
    }

    #[Override]
    public function export(Scope $scope, ImageWriterInterface $images): array
    {
        if ($scope->cmsIds === []) {
            return [];
        }

        $rows = [];
        foreach ($this->em->getRepository(Cms::class)->findBy(['id' => $scope->cmsIds], ['id' => 'ASC']) as $page) {
            $titles = [];
            foreach ($page->getTitles() as $title) {
                $titles[$title->getLanguage()] = $title->getTitle();
            }
            ksort($titles);

            $linkNames = [];
            foreach ($page->getLinkNames() as $linkName) {
                $linkNames[$linkName->getLanguage()] = $linkName->getName();
            }
            ksort($linkNames);

            $menuLocations = [];
            foreach ($page->getMenuLocations() as $menuLocation) {
                $menuLocations[] = $menuLocation->getLocation()->value;
            }
            sort($menuLocations);

            $pageBlocks = $page->getBlocks()->toArray();
            usort($pageBlocks, static fn(CmsBlock $a, CmsBlock $b): int => $a->getId() <=> $b->getId());

            $blocks = [];
            foreach ($pageBlocks as $block) {
                $blocks[] = [
                    'language' => $block->getLanguage(),
                    'type' => $block->getType()->value,
                    'priority' => $block->getPriority(),
                    'json' => $block->getJson(),
                    'image_file' => $block->getImage() instanceof Image ? $images->addImage($block->getImage()) : null,
                ];
            }

            $rows[] = [
                'ref' => $page->getId(),
                'slug' => $page->getSlug(),
                'published' => $page->isPublished(),
                'titles' => $titles,
                'link_names' => $linkNames,
                'menu_locations' => $menuLocations,
                'blocks' => $blocks,
            ];
        }

        return $rows;
    }

    #[Override]
    public function import(array $rows, ImportContext $context): void
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $ref = (int) ($row['ref'] ?? 0);
            $slug = (string) ($row['slug'] ?? '');
            $existing = $slug !== '' ? $this->em->getRepository(Cms::class)->findOneBy(['slug' => $slug]) : null;
            if ($existing instanceof Cms) {
                $context->mapRef(Cms::class, $ref, $existing);
                $context->count($this->getKey(), Outcome::Skipped);
                continue;
            }

            $page = new Cms();
            $page->setSlug($slug !== '' ? $slug : null);
            $page->setPublished((bool) ($row['published'] ?? false));
            $page->setLocked(false);
            $page->setCreatedAt(new DateTimeImmutable());
            $page->setCreatedBy($context->getSystemUser());
            $this->addLabels($page, $row);
            $this->em->persist($page);

            foreach (is_array($row['blocks'] ?? null) ? $row['blocks'] : [] as $blockRow) {
                if (!is_array($blockRow)) {
                    continue;
                }

                $this->addBlock($page, $blockRow, $context);
            }

            $context->mapRef(Cms::class, $ref, $page);
            $context->count($this->getKey(), Outcome::Created);
        }
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function addLabels(Cms $page, array $row): void
    {
        foreach (is_array($row['titles'] ?? null) ? $row['titles'] : [] as $language => $title) {
            $cmsTitle = new CmsTitle();
            $cmsTitle->setLanguage((string) $language);
            $cmsTitle->setTitle((string) $title);
            $page->addTitle($cmsTitle);
        }

        foreach (is_array($row['link_names'] ?? null) ? $row['link_names'] : [] as $language => $name) {
            $cmsLinkName = new CmsLinkName();
            $cmsLinkName->setLanguage((string) $language);
            $cmsLinkName->setName((string) $name);
            $page->addLinkName($cmsLinkName);
        }

        foreach (is_array($row['menu_locations'] ?? null) ? $row['menu_locations'] : [] as $locationValue) {
            $menuLocation = MenuLocation::tryFrom((int) $locationValue);
            if ($menuLocation === null) {
                continue;
            }

            $cmsMenuLocation = new CmsMenuLocation();
            $cmsMenuLocation->setLocation($menuLocation);
            $page->addMenuLocation($cmsMenuLocation);
        }
    }

    /**
     * @param array<array-key, mixed> $blockRow
     */
    private function addBlock(Cms $page, array $blockRow, ImportContext $context): void
    {
        $blockType = CmsBlockType::tryFrom((int) ($blockRow['type'] ?? 0));
        if ($blockType === null) {
            return;
        }

        $block = new CmsBlock();
        $block->setLanguage((string) ($blockRow['language'] ?? 'en'));
        $block->setType($blockType);
        $block->setPriority((float) ($blockRow['priority'] ?? 1.0));
        $block->setJson(is_array($blockRow['json'] ?? null) ? $blockRow['json'] : []);
        $block->setPage($page);

        $image = $context->importImage($blockRow['image_file'] ?? null, ImageType::CmsBlock);
        if ($image instanceof Image) {
            $block->setImage($image);
        }

        $this->em->persist($block);
    }
}
