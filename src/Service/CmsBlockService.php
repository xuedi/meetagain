<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\CmsBlockTypes;
use App\Repository\CmsBlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

readonly class CmsBlockService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CmsBlockRepository $blockRepo,
    ) {
    }

    public function createBlock(Cms $page, string $locale, CmsBlockTypes $type, array $payload): CmsBlock
    {
        $blockObject = CmsBlockTypes::buildObject($type, $payload);

        $block = new CmsBlock();
        $block->setLanguage($locale);
        $block->setPriority($this->blockRepo->getMaxPriority() + 1);
        $block->setType($blockObject::getType());
        $block->setJson($blockObject->toArray());

        $page->addBlock($block);
        $this->em->persist($block);
        $this->em->flush();

        return $block;
    }

    public function updateBlock(int $blockId, CmsBlockTypes $type, array $payload): CmsBlock
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw new RuntimeException('Could not load block');
        }

        $block->setJson(CmsBlockTypes::buildObject($type, $payload)->toArray());
        $this->em->persist($block);
        $this->em->flush();

        return $block;
    }

    public function deleteBlock(int $blockId): void
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw new RuntimeException('Could not load block');
        }

        $this->em->remove($block);
        $this->em->flush();
    }

    public function moveBlockUp(int $pageId, int $blockId, string $locale): void
    {
        $this->adjustPriority($pageId, $blockId, $locale, -1.5);
    }

    public function moveBlockDown(int $pageId, int $blockId, string $locale): void
    {
        $this->adjustPriority($pageId, $blockId, $locale, 1.5);
    }

    private function adjustPriority(int $pageId, int $blockId, string $locale, float $offset): void
    {
        $block = $this->blockRepo->find($blockId);
        if ($block === null) {
            throw new RuntimeException('Could not load block');
        }

        $block->setPriority($block->getPriority() + $offset);
        $this->em->persist($block);
        $this->em->flush();

        $this->reorderBlocks($pageId, $locale);
    }

    private function reorderBlocks(int $pageId, string $locale): void
    {
        $blocks = $this->blockRepo->findBy(
            ['page' => $pageId, 'language' => $locale],
            ['priority' => 'ASC']
        );

        $priority = 1;
        foreach ($blocks as $block) {
            $block->setPriority($priority);
            $priority++;
            $this->em->persist($block);
        }
        $this->em->flush();
    }
}
