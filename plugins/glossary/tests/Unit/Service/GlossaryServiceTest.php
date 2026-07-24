<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use App\EntityActionDispatcher;
use App\Enum\ItemAction;
use App\Item\ItemActionDispatcher;
use App\Item\ItemFilterService;
use App\Item\Taxonomy\ItemTaxonomyService;
use App\Review\ChangeProposalService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryCategorizableTypeProvider;
use Plugin\Glossary\Repository\GlossaryRepository;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\GlossaryService;
use RuntimeException;

class GlossaryServiceTest extends TestCase
{
    public function testCreateAutoApprovesAndStampsOwner(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em, $this->createStub(GlossaryRepository::class));
        $glossary = (new Glossary())->setPhrase('你好');

        // Act
        $service->create($glossary, userId: 9, autoApprove: true);

        // Assert
        self::assertTrue($glossary->getApproved());
        self::assertSame(9, $glossary->getCreatedBy());
        self::assertNotNull($glossary->getCreatedAt());
    }

    public function testCreateWithoutAutoApproveLeavesEntryPending(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->makeService($em, $this->createStub(GlossaryRepository::class));
        $glossary = (new Glossary())->setPhrase('你好');

        // Act
        $service->create($glossary, userId: 9, autoApprove: false);

        // Assert
        self::assertFalse($glossary->getApproved());
    }

    public function testApproveNewMarksEntryApproved(): void
    {
        // Arrange
        $item = (new Glossary())->setApproved(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($item);
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em, $this->repoReturning($item));

        // Act
        $service->approveNew(1);

        // Assert
        self::assertTrue($item->getApproved());
    }

    public function testDeleteNewRejectsApprovedEntry(): void
    {
        // Arrange
        $item = (new Glossary())->setApproved(true);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');
        $service = $this->makeService($em, $this->repoReturning($item));

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->deleteNew(1);
    }

    public function testDeleteNewRemovesPendingEntry(): void
    {
        // Arrange
        $item = (new Glossary())->setApproved(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($item);
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em, $this->repoReturning($item));

        // Act
        $service->deleteNew(1);

        // Assert — mock verifies remove()/flush() were called
    }

    public function testDeleteRemovesEntryAndItsPendingProposals(): void
    {
        // Arrange
        $item = new Glossary();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($item);
        $em->expects(self::once())->method('flush');
        $proposals = $this->createMock(ChangeProposalService::class);
        $proposals->expects(self::once())
            ->method('removeForTarget')
            ->with(GlossaryCategorizableTypeProvider::ITEM_TYPE, 1);
        $service = $this->makeService($em, $this->repoReturning($item), changeProposalService: $proposals);

        // Act
        $service->delete(1);
    }

    public function testApplyChangeWritesScalarField(): void
    {
        // Arrange
        $item = (new Glossary())->setPhrase('old');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($item);
        $em->expects(self::once())->method('flush');
        $service = $this->makeService($em, $this->repoReturning($item));

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_PHRASE, 'brandnew');

        // Assert
        self::assertSame('brandnew', $item->getPhrase());
    }

    public function testApplyChangeEmptyPinyinClearsTheField(): void
    {
        // Arrange
        $item = (new Glossary())->setPinyin('lǎo');
        $em = $this->createStub(EntityManagerInterface::class);
        $service = $this->makeService($em, $this->repoReturning($item));

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_PINYIN, '');

        // Assert
        self::assertNull($item->getPinyin());
    }

    public function testApplyChangeRoutesCategoryThroughTheTaxonomy(): void
    {
        // Arrange
        $taxonomy = $this->createMock(ItemTaxonomyService::class);
        $taxonomy->expects(self::once())
            ->method('setCategory')
            ->with(GlossaryCategorizableTypeProvider::ITEM_TYPE, 1, 3);
        $service = $this->makeService(
            $this->createStub(EntityManagerInterface::class),
            $this->repoReturning(new Glossary()),
            taxonomyService: $taxonomy,
        );

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_CATEGORY, '3');
    }

    public function testApplyChangeRejectsUnknownField(): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning(new Glossary()));

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->applyChange(1, 'unknown', 'value');
    }

    public function testApplyChangeThrowsWhenEntryIsGone(): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning(null));

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_PHRASE, 'value');
    }

    public function testListNarrowsThroughTheCoreItemFilterChain(): void
    {
        // Arrange
        $filter = $this->createMock(ItemFilterService::class);
        $filter->expects(self::once())
            ->method('getAllowedItemIds')
            ->with(GlossaryCategorizableTypeProvider::ITEM_TYPE)
            ->willReturn([4, 7]);

        $entry = (new Glossary())->setPhrase('你好');
        $repo = $this->createMock(GlossaryRepository::class);
        $repo->expects(self::once())
            ->method('findAllowed')
            ->with([4, 7], ['phrase' => 'ASC'])
            ->willReturn([$entry]);

        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $repo, $filter);

        // Act
        $list = $service->getList();

        // Assert
        self::assertSame([$entry], $list);
    }

    public function testCreateAnnouncesTheNewItemToTheItemActionChain(): void
    {
        // Arrange
        $dispatcher = $this->createMock(ItemActionDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(ItemAction::Created, GlossaryCategorizableTypeProvider::ITEM_TYPE, 0);

        $service = $this->makeService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(GlossaryRepository::class),
            itemActionDispatcher: $dispatcher,
        );

        // Act
        $service->create((new Glossary())->setPhrase('你好'), userId: 9);
    }

    private function makeService(
        EntityManagerInterface $em,
        GlossaryRepository $repo,
        ?ItemFilterService $filter = null,
        ?ItemActionDispatcher $itemActionDispatcher = null,
        ?ItemTaxonomyService $taxonomyService = null,
        ?ChangeProposalService $changeProposalService = null,
    ): GlossaryService {
        if ($filter === null) {
            $filter = $this->createStub(ItemFilterService::class);
            $filter->method('getAllowedItemIds')->willReturn(null);
        }

        return new GlossaryService(
            $em,
            $repo,
            $filter,
            $this->createStub(EntityActionDispatcher::class),
            $taxonomyService ?? $this->createStub(ItemTaxonomyService::class),
            $itemActionDispatcher ?? $this->createStub(ItemActionDispatcher::class),
            $changeProposalService ?? $this->createStub(ChangeProposalService::class),
        );
    }

    private function repoReturning(?Glossary $item): GlossaryRepository
    {
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findOneAllowed')->willReturn($item);

        return $repo;
    }
}
