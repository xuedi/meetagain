<?php declare(strict_types=1);

namespace Tests\Unit\Review;

use App\Activity\ActivityService;
use App\Activity\Messages\ChangeProposalApplied;
use App\Activity\Messages\ChangeProposalCreated;
use App\Activity\Messages\ChangeProposalRejected;
use App\Entity\ChangeProposal;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use App\Repository\ChangeProposalRepository;
use App\Review\ChangeProposalService;
use App\Review\ChangeTargetProviderInterface;
use App\Review\ChangeTargetRegistry;
use App\Review\FieldChange;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ChangeProposalServiceTest extends TestCase
{
    public function testProposeSkipsWhenNothingChanged(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $service = $this->makeService($em, provider: $this->provider());

        // Act
        $result = $service->propose('glossary', 1, $this->user(5), [
            new FieldChange('phrase', 'same', 'same'),
        ]);

        // Assert
        self::assertNull($result);
    }

    public function testProposeStoresOnlyChangedFieldsAndLogsActivity(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')
            ->with(ChangeProposalCreated::TYPE, self::isInstanceOf(User::class), self::arrayHasKey('target_label'));
        $service = $this->makeService($em, activity: $activity, provider: $this->provider());

        // Act
        $proposal = $service->propose('glossary', 1, $this->user(5), [
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('pinyin', 'same', 'same'),
        ]);

        // Assert
        self::assertInstanceOf(ChangeProposal::class, $proposal);
        self::assertCount(1, $proposal->getChanges());
        self::assertSame('phrase', $proposal->getChanges()[0]->field);
        self::assertSame(ChangeProposalStatus::Pending, $proposal->getStatus());
    }

    public function testProposeDeniedWithoutPermission(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider(canPropose: false));

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $service->propose('glossary', 1, $this->user(5), [new FieldChange('phrase', 'old', 'new')]);
    }

    public function testProposeThrowsForUnregisteredTargetType(): void
    {
        // Arrange
        $service = $this->makeService(provider: null);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->propose('unknown', 1, $this->user(5), [new FieldChange('phrase', 'old', 'new')]);
    }

    public function testApplyLastFieldFinalizesToApproved(): void
    {
        // Arrange
        $provider = $this->createMock(ChangeTargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $provider->method('validate')->willReturn(null);
        $provider->method('getTargetLabel')->willReturn('entry');
        $provider->expects(self::once())->method('apply')->with(1, 'phrase', 'new');
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')
            ->with(ChangeProposalApplied::TYPE, self::isInstanceOf(User::class), self::anything());
        $service = $this->makeService(activity: $activity, provider: $provider);
        $proposal = $this->proposal([new FieldChange('phrase', 'old', 'new')]);
        $reviewer = $this->user(9);

        // Act
        $service->applyField($proposal, 'phrase', $reviewer);

        // Assert
        self::assertSame(ChangeProposalStatus::Approved, $proposal->getStatus());
        self::assertSame($reviewer, $proposal->getReviewedBy());
        self::assertNotNull($proposal->getReviewedAt());
    }

    public function testApplyFieldStopsOnValidationError(): void
    {
        // Arrange
        $provider = $this->createMock(ChangeTargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $provider->method('validate')->willReturn('category is gone');
        $provider->expects(self::never())->method('apply');
        $service = $this->makeService(provider: $provider);
        $proposal = $this->proposal([new FieldChange('category', '3', '99')]);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('category is gone');

        // Act
        $service->applyField($proposal, 'category', $this->user(9));
    }

    public function testPartialResolutionKeepsProposalPending(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider(canReview: true));
        $proposal = $this->proposal([
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('pinyin', 'a', 'b'),
        ]);

        // Act
        $service->applyField($proposal, 'phrase', $this->user(9));

        // Assert
        self::assertSame(ChangeProposalStatus::Pending, $proposal->getStatus());
        self::assertNull($proposal->getReviewedBy());
    }

    public function testDenyingEveryFieldFinalizesToRejected(): void
    {
        // Arrange
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')
            ->with(ChangeProposalRejected::TYPE, self::isInstanceOf(User::class), self::anything());
        $service = $this->makeService(activity: $activity, provider: $this->provider(canReview: true));
        $proposal = $this->proposal([
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('pinyin', 'a', 'b'),
        ]);
        $reviewer = $this->user(9);

        // Act
        $service->denyField($proposal, 'phrase', $reviewer);
        $service->denyField($proposal, 'pinyin', $reviewer);

        // Assert
        self::assertSame(ChangeProposalStatus::Rejected, $proposal->getStatus());
    }

    public function testReviewDeniedWithoutPermission(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider(canReview: false));
        $proposal = $this->proposal([new FieldChange('phrase', 'old', 'new')]);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $service->applyField($proposal, 'phrase', $this->user(9));
    }

    public function testApproveAllValidatesEveryFieldBeforeApplyingAny(): void
    {
        // Arrange
        $provider = $this->createMock(ChangeTargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $provider->method('validate')->willReturnCallback(
            static fn(int $id, string $field, ?string $value): ?string => $field === 'pinyin' ? 'broken' : null,
        );
        $provider->expects(self::never())->method('apply');
        $service = $this->makeService(provider: $provider);
        $proposal = $this->proposal([
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('pinyin', 'a', 'b'),
        ]);

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->approveAll($proposal, $this->user(9));
    }

    public function testRejectAllFinalizesToRejected(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider(canReview: true));
        $proposal = $this->proposal([
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('pinyin', 'a', 'b'),
        ]);

        // Act
        $service->rejectAll($proposal, $this->user(9));

        // Assert
        self::assertSame(ChangeProposalStatus::Rejected, $proposal->getStatus());
    }

    public function testWithdrawByProposerMarksWithdrawn(): void
    {
        // Arrange
        $proposer = $this->user(5);
        $service = $this->makeService(provider: $this->provider());
        $proposal = $this->proposal([new FieldChange('phrase', 'old', 'new')], $proposer);

        // Act
        $service->withdraw($proposal, $proposer);

        // Assert
        self::assertSame(ChangeProposalStatus::Withdrawn, $proposal->getStatus());
    }

    public function testWithdrawByAnotherUserIsDenied(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider());
        $proposal = $this->proposal([new FieldChange('phrase', 'old', 'new')], $this->user(5));

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $service->withdraw($proposal, $this->user(6));
    }

    public function testWithdrawRejectsResolvedProposal(): void
    {
        // Arrange
        $proposer = $this->user(5);
        $service = $this->makeService(provider: $this->provider());
        $proposal = $this->proposal([new FieldChange('phrase', 'old', 'new')], $proposer);
        $proposal->setStatus(ChangeProposalStatus::Approved);

        // Assert
        $this->expectException(RuntimeException::class);

        // Act
        $service->withdraw($proposal, $proposer);
    }

    public function testPendingReviewableByFiltersProviderAndPermission(): void
    {
        // Arrange
        $reviewable = $this->proposal([new FieldChange('phrase', 'old', 'new')]);
        $foreign = $this->proposal([new FieldChange('phrase', 'old', 'new')]);
        $foreign->setTargetType('other');

        $provider = $this->createStub(ChangeTargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $provider->method('getTargetLabel')->willReturn('entry');
        $registry = $this->createStub(ChangeTargetRegistry::class);
        $registry->method('providerFor')->willReturnCallback(
            static fn(string $type): ?ChangeTargetProviderInterface => $type === 'glossary' ? $provider : null,
        );
        $repo = $this->createStub(ChangeProposalRepository::class);
        $repo->method('findPending')->willReturn([$reviewable, $foreign]);

        $service = new ChangeProposalService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $registry,
            $this->createStub(ActivityService::class),
        );

        // Act
        $result = $service->pendingReviewableBy($this->user(9));

        // Assert
        self::assertSame([$reviewable], $result);
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?ActivityService $activity = null,
        ?ChangeTargetProviderInterface $provider = null,
    ): ChangeProposalService {
        $registry = $this->createStub(ChangeTargetRegistry::class);
        $registry->method('providerFor')->willReturn($provider);

        return new ChangeProposalService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(ChangeProposalRepository::class),
            $registry,
            $activity ?? $this->createStub(ActivityService::class),
        );
    }

    private function provider(bool $canPropose = true, bool $canReview = true): ChangeTargetProviderInterface&Stub
    {
        $provider = $this->createStub(ChangeTargetProviderInterface::class);
        $provider->method('canPropose')->willReturn($canPropose);
        $provider->method('canReview')->willReturn($canReview);
        $provider->method('validate')->willReturn(null);
        $provider->method('getTargetLabel')->willReturn('entry');

        return $provider;
    }

    /** @param list<FieldChange> $changes */
    private function proposal(array $changes, ?User $proposer = null): ChangeProposal
    {
        $proposal = new ChangeProposal();
        $proposal->setTargetType('glossary');
        $proposal->setTargetId(1);
        $proposal->setProposedBy($proposer ?? $this->user(5));
        $proposal->setChanges($changes);

        return $proposal;
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
