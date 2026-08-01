<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Service;

use App\EntityActionDispatcher;
use App\Enum\ItemAction;
use App\Item\ActionDispatcher;
use App\Item\AdminFilterService;
use App\Item\FilterService;
use App\Item\Tag\TagService;
use App\Review\ChangeProposalService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Item\GlossaryTaggableTypeProvider;
use Plugin\Glossary\Repository\GlossaryRepository;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\GlossaryService;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;

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

        // Assert
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
            ->with(GlossaryTaggableTypeProvider::ITEM_TYPE, 1);
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

    public function testApplyChangeRoutesEveryProposedTagThroughTheTagService(): void
    {
        // Arrange
        $tagService = $this->createMock(TagService::class);
        $tagService->expects(self::once())
            ->method('setTags')
            ->with(GlossaryTaggableTypeProvider::ITEM_TYPE, 1, [3, 7]);
        $service = $this->makeService(
            $this->createStub(EntityManagerInterface::class),
            $this->repoReturning(new Glossary()),
            tagService: $tagService,
        );

        // Act
        $service->applyChange(1, GlossaryChangeTarget::FIELD_TAG, '3,7');
    }

    public function testTagIdsRoundTripThroughTheProposalValue(): void
    {
        // Arrange
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $this->repoReturning(new Glossary()));

        // Act & Assert
        self::assertSame('3,7', $service->encodeTagIds([7, 3]));
        self::assertNull($service->encodeTagIds([]));
        self::assertSame([3, 7], $service->decodeTagIds('3,7'));
        self::assertSame([], $service->decodeTagIds(null));
        self::assertSame([], $service->decodeTagIds(''));
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
        $filter = $this->createMock(FilterService::class);
        $filter->expects(self::once())
            ->method('getAllowedItemIds')
            ->with(GlossaryTaggableTypeProvider::ITEM_TYPE)
            ->willReturn([4, 7]);

        $entry = (new Glossary())->setPhrase('你好');
        $repo = $this->createMock(GlossaryRepository::class);
        $repo->expects(self::once())
            ->method('findAllowed')
            ->with([4, 7], ['phrase' => 'ASC'], true)
            ->willReturn([$entry]);

        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $repo, $filter);

        // Act
        $list = $service->getList();

        // Assert
        self::assertSame([$entry], $list);
    }

    public function testReadPathNarrowsToApprovedEntriesForOrdinaryViewers(): void
    {
        // Arrange
        $repo = $this->createMock(GlossaryRepository::class);
        $repo->expects(self::once())->method('findOneAllowed')->with(4, null, true)->willReturn(null);
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $entry = $service->get(4);

        // Assert
        self::assertNull($entry);
    }

    public function testReadPathKeepsUnapprovedEntriesVisibleToModerators(): void
    {
        // Arrange
        $pending = (new Glossary())->setApproved(false);
        $repo = $this->createMock(GlossaryRepository::class);
        $repo->expects(self::once())->method('findOneAllowed')->with(4, null, false)->willReturn($pending);
        $service = $this->makeService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            security: $this->viewer(moderator: true),
        );

        // Act
        $entry = $service->get(4);

        // Assert
        self::assertSame($pending, $entry);
    }

    public function testListKeepsUnapprovedEntriesVisibleToModerators(): void
    {
        // Arrange
        $repo = $this->createMock(GlossaryRepository::class);
        $repo->expects(self::once())->method('findAllowed')->with(null, ['phrase' => 'ASC'], false)->willReturn([]);
        $service = $this->makeService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            security: $this->viewer(moderator: true),
        );

        // Act
        $service->getList();
    }

    public function testApproveNewReachesPendingEntriesWithoutAModeratorToken(): void
    {
        // Arrange
        $pending = (new Glossary())->setApproved(false);
        $repo = $this->createMock(GlossaryRepository::class);
        $repo->expects(self::once())->method('findOneAllowed')->with(1, null)->willReturn($pending);
        $service = $this->makeService($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $service->approveNew(1);

        // Assert
        self::assertTrue($pending->getApproved());
    }

    public function testCreateAnnouncesTheNewItemToTheItemActionChain(): void
    {
        // Arrange
        $dispatcher = $this->createMock(ActionDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(ItemAction::Created, GlossaryTaggableTypeProvider::ITEM_TYPE, 0);

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
        ?FilterService $filter = null,
        ?AdminFilterService $adminFilter = null,
        ?ActionDispatcher $itemActionDispatcher = null,
        ?TagService $tagService = null,
        ?ChangeProposalService $changeProposalService = null,
        ?Security $security = null,
    ): GlossaryService {
        if ($filter === null) {
            $filter = $this->createStub(FilterService::class);
            $filter->method('getAllowedItemIds')->willReturn(null);
        }

        if ($adminFilter === null) {
            $adminFilter = $this->createStub(AdminFilterService::class);
            $adminFilter->method('getAllowedItemIds')->willReturn(null);
        }

        return new GlossaryService(
            $em,
            $repo,
            $filter,
            $adminFilter,
            $this->createStub(EntityActionDispatcher::class),
            $tagService ?? $this->createStub(TagService::class),
            $itemActionDispatcher ?? $this->createStub(ActionDispatcher::class),
            $changeProposalService ?? $this->createStub(ChangeProposalService::class),
            $security ?? $this->viewer(moderator: false),
        );
    }

    private function viewer(bool $moderator): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($moderator);

        return $security;
    }

    private function repoReturning(?Glossary $item): GlossaryRepository
    {
        $repo = $this->createStub(GlossaryRepository::class);
        $repo->method('findOneAllowed')->willReturn($item);

        return $repo;
    }
}
