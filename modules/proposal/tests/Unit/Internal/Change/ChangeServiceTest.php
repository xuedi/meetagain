<?php declare(strict_types=1);

namespace Module\Proposal\Tests\Unit\Internal\Change;

use App\Activity\ActivityService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Proposal\Contract\Change\FieldChange;
use Module\Proposal\Contract\Change\TargetProviderInterface;
use Module\Proposal\Contract\Status;
use Module\Proposal\Internal\Activity\Change\Applied;
use Module\Proposal\Internal\Activity\Change\Created;
use Module\Proposal\Internal\Activity\Change\Rejected;
use Module\Proposal\Internal\Change\ChangeService;
use Module\Proposal\Internal\Change\Registry;
use Module\Proposal\Internal\Entity\Change;
use Module\Proposal\Internal\Repository\ChangeRepository;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ChangeServiceTest extends TestCase
{
    public function testProposeSkipsWhenNothingChanged(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $service = $this->makeService($em, provider: $this->provider());

        // Act
        $result = $service->propose('glossary', 1, 5, [
            new FieldChange('phrase', 'same', 'same'),
        ]);

        // Assert
        self::assertNull($result);
    }

    public function testProposeStoresOnlyChangedFieldsAndLogsActivity(): void
    {
        // Arrange
        $proposal = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($this->user(5));
        $em
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Change $persisted) use (&$proposal): bool {
                $proposal = $persisted;

                return true;
            }));
        $em->expects(self::once())->method('flush');
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(Created::TYPE, self::isInstanceOf(User::class), self::arrayHasKey('target_label'));
        $service = $this->makeService($em, activity: $activity, provider: $this->provider());

        // Act
        $service->propose('glossary', 1, 5, [
            new FieldChange('phrase', 'old', 'new'),
            new FieldChange('pinyin', 'same', 'same'),
        ]);

        // Assert
        self::assertInstanceOf(Change::class, $proposal);
        self::assertCount(1, $proposal->getChanges());
        self::assertSame('phrase', $proposal->getChanges()[0]->field);
        self::assertSame(Status::Pending, $proposal->getStatus());
    }

    public function testProposeDeniedWithoutPermission(): void
    {
        // Arrange
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($this->user(5));
        $service = $this->makeService($em, provider: $this->provider(canPropose: false));

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $service->propose('glossary', 1, 5, [new FieldChange('phrase', 'old', 'new')]);
    }

    public function testProposeThrowsForUnregisteredTargetType(): void
    {
        // Arrange
        $service = $this->makeService(provider: null);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->propose('unknown', 1, 5, [new FieldChange('phrase', 'old', 'new')]);
    }

    public function testApplyLastFieldFinalizesToApproved(): void
    {
        // Arrange
        $provider = $this->createMock(TargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $provider->method('validate')->willReturn(null);
        $provider->method('getTargetLabel')->willReturn('entry');
        $provider->expects(self::once())->method('apply')->with(1, 'phrase', 'new');
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(Applied::TYPE, self::isInstanceOf(User::class), self::anything());
        $service = $this->makeService(activity: $activity, provider: $provider);
        $proposal = $this->proposal([new FieldChange('phrase', 'old', 'new')]);
        $reviewer = $this->user(9);

        // Act
        $service->applyField($proposal, 'phrase', $reviewer);

        // Assert
        self::assertSame(Status::Approved, $proposal->getStatus());
        self::assertSame($reviewer, $proposal->getReviewedBy());
        self::assertNotNull($proposal->getReviewedAt());
    }

    public function testApplyFieldStopsOnValidationError(): void
    {
        // Arrange
        $provider = $this->createMock(TargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $provider->method('validate')->willReturn('category is gone');
        $provider->expects(self::never())->method('apply');
        $service = $this->makeService(provider: $provider);
        $proposal = $this->proposal([new FieldChange('category', '3', '99')]);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('category is gone');

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
        self::assertSame(Status::Pending, $proposal->getStatus());
        self::assertNull($proposal->getReviewedBy());
    }

    public function testDenyingEveryFieldFinalizesToRejected(): void
    {
        // Arrange
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(Rejected::TYPE, self::isInstanceOf(User::class), self::anything());
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
        self::assertSame(Status::Rejected, $proposal->getStatus());
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
        $provider = $this->createMock(TargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $provider->method('validate')->willReturnCallback(static fn(int $id, string $field, ?string $value): ?string => $field === 'pinyin' ? 'broken' : null);
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
        self::assertSame(Status::Rejected, $proposal->getStatus());
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
        self::assertSame(Status::Withdrawn, $proposal->getStatus());
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
        $proposal->setStatus(Status::Approved);

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

        $provider = $this->createStub(TargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $provider->method('getTargetLabel')->willReturn('entry');
        $registry = $this->createStub(Registry::class);
        $registry->method('providerFor')->willReturnCallback(static fn(string $type): ?TargetProviderInterface => $type === 'glossary' ? $provider : null);
        $repo = $this->createStub(ChangeRepository::class);
        $repo->method('findPending')->willReturn([$reviewable, $foreign]);

        $service = new ChangeService($this->createStub(EntityManagerInterface::class), $repo, $registry, $this->createStub(ActivityService::class));

        // Act
        $result = $service->pendingReviewableBy($this->user(9));

        // Assert
        self::assertSame([$reviewable], $result);
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?ActivityService $activity = null,
        ?TargetProviderInterface $provider = null,
    ): ChangeService {
        $registry = $this->createStub(Registry::class);
        $registry->method('providerFor')->willReturn($provider);

        return new ChangeService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(ChangeRepository::class),
            $registry,
            $activity ?? $this->createStub(ActivityService::class),
        );
    }

    private function provider(bool $canPropose = true, bool $canReview = true): TargetProviderInterface&Stub
    {
        $provider = $this->createStub(TargetProviderInterface::class);
        $provider->method('canPropose')->willReturn($canPropose);
        $provider->method('canReview')->willReturn($canReview);
        $provider->method('validate')->willReturn(null);
        $provider->method('getTargetLabel')->willReturn('entry');

        return $provider;
    }

    /** @param list<FieldChange> $changes */
    private function proposal(array $changes, ?User $proposer = null): Change
    {
        $proposal = new Change();
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
