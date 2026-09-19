<?php declare(strict_types=1);

namespace App\Service\Cms;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\EntityActionDispatcher;
use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\EntityAction;
use App\Repository\CmsBlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

readonly class CmsBlockService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CmsBlockRepository $blockRepo,
        private BlockHydrator $hydrator,
        private EntityActionDispatcher $entityActionDispatcher,
    ) {}

    public function createBlock(Cms $page, string $locale, CmsBlockType $type, array $payload): CmsBlock
    {
        $blockObject = $this->hydrator->hydrate($type, $payload);

        $block = new CmsBlock();
        $block->setLanguage($locale);
        $block->setPriority(99999);
        $block->setType($blockObject::getType());
        $block->setJson($blockObject->toArray());

        $page->addBlock($block);
        $this->em->persist($block);
        $this->em->flush();

        $this->reorderBlocks($page->getId(), $locale);
        $this->entityActionDispatcher->dispatch(EntityAction::UpdateCmsBlock, (int) $page->getId());

        return $block;
    }

    public function updateBlock(CmsBlock $block, CmsBlockType $type, array $payload): CmsBlock
    {
        $pageId = $this->pageIdOf($block);

        $block->setJson($this->hydrator->hydrate($type, $payload, $block->getImage())->toArray());
        $this->em->persist($block);
        $this->em->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::UpdateCmsBlock, $pageId);

        return $block;
    }

    public function deleteBlock(CmsBlock $block): void
    {
        $pageId = $this->pageIdOf($block);

        $this->em->remove($block);
        $this->em->flush();

        $this->entityActionDispatcher->dispatch(EntityAction::UpdateCmsBlock, $pageId);
    }

    public function moveBlockUp(CmsBlock $block): void
    {
        $this->adjustPriority($block, -1.5);
    }

    public function moveBlockDown(CmsBlock $block): void
    {
        $this->adjustPriority($block, 1.5);
    }

    private function adjustPriority(CmsBlock $block, float $offset): void
    {
        $pageId = $this->pageIdOf($block);

        $block->setPriority($block->getPriority() + $offset);
        $this->em->persist($block);
        $this->em->flush();

        $this->reorderBlocks($pageId, (string) $block->getLanguage());
        $this->entityActionDispatcher->dispatch(EntityAction::UpdateCmsBlock, $pageId);
    }

    private function pageIdOf(CmsBlock $block): int
    {
        $page = $block->getPage();
        if ($page === null) {
            throw new RuntimeException('Could not load the page owning this block');
        }

        return (int) $page->getId();
    }

    private function reorderBlocks(?int $pageId, string $locale): void
    {
        $blocks = $this->blockRepo->findBy(['page' => $pageId, 'language' => $locale], ['priority' => 'ASC']);

        $priority = 1;
        foreach ($blocks as $block) {
            $block->setPriority($priority);
            ++$priority;
            $this->em->persist($block);
        }
        $this->em->flush();
    }
}
