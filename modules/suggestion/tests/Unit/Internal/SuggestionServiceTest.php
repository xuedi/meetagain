<?php declare(strict_types=1);

namespace Module\Suggestion\Tests\Unit\Internal;

use App\Activity\ActivityService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Module\Suggestion\Contract\PortableSuggestion;
use Module\Suggestion\Contract\Status;
use Module\Suggestion\Contract\TargetProviderInterface;
use Module\Suggestion\Internal\Activity\Approved;
use Module\Suggestion\Internal\Activity\Created;
use Module\Suggestion\Internal\Activity\Rejected;
use Module\Suggestion\Internal\Entity\Suggestion;
use Module\Suggestion\Internal\Registry;
use Module\Suggestion\Internal\Repository\SuggestionRepository;
use Module\Suggestion\Internal\SuggestionException;
use Module\Suggestion\Internal\SuggestionService;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SuggestionServiceTest extends TestCase
{
    public function testProposeStoresTheProviderPayloadAndLogsActivity(): void
    {
        // Arrange
        $stored = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getReference')->willReturn($this->user(5));
        $em
            ->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$stored): void {
                $stored = $entity;
            });
        $em->expects(self::once())->method('flush');
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(Created::TYPE, self::isInstanceOf(User::class), self::arrayHasKey('description'));
        $service = $this->makeService($em, activity: $activity, provider: $this->provider());

        // Act
        $error = $service->propose('location', 5, new stdClass());

        // Assert
        self::assertNull($error);
        self::assertInstanceOf(Suggestion::class, $stored);
        self::assertSame('location', $stored->getTargetType());
        self::assertSame(Status::Pending, $stored->getStatus());
        self::assertSame(['name' => 'Cafe'], $stored->getPayload());
    }

    public function testProposeIsDeniedWithoutPermission(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider(canPropose: false));

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $service->propose('location', 5, new stdClass());
    }

    public function testProposeReturnsTheErrorOfAnInvalidDraftAndStoresNothing(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $service = $this->makeService($em, provider: $this->provider(validationError: 'that venue already exists'));

        // Act
        $error = $service->propose('location', 5, new stdClass());

        // Assert
        self::assertSame('that venue already exists', $error);
    }

    public function testProposeThrowsForAnUnregisteredTargetType(): void
    {
        // Arrange
        $service = $this->makeService(provider: null);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->propose('unknown', 5, new stdClass());
    }

    public function testApproveCreatesTheRowAndRecordsItsId(): void
    {
        // Arrange
        $provider = $this->provider();
        $provider->method('create')->willReturn(42);
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(Approved::TYPE, self::isInstanceOf(User::class), self::anything());
        $service = $this->makeService(activity: $activity, provider: $provider);
        $suggestion = $this->suggestion();
        $reviewer = $this->user(9);

        // Act
        $createdId = $service->approve($suggestion, new stdClass(), $reviewer);

        // Assert
        self::assertSame(42, $createdId);
        self::assertSame(42, $suggestion->getCreatedId());
        self::assertSame(Status::Approved, $suggestion->getStatus());
        self::assertSame($reviewer, $suggestion->getReviewedBy());
        self::assertNotNull($suggestion->getResolvedAt());
    }

    public function testApproveRevalidatesBeforeCreating(): void
    {
        // Arrange
        $provider = $this->provider(validationError: 'a venue with that name appeared meanwhile', expectNoCreate: true);
        $service = $this->makeService(provider: $provider);

        // Assert
        $this->expectException(SuggestionException::class);

        // Act
        $service->approve($this->suggestion(), new stdClass(), $this->user(9));
    }

    public function testApproveIsDeniedWithoutReviewPermission(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider(canReview: false));

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $service->approve($this->suggestion(), new stdClass(), $this->user(9));
    }

    public function testApproveRejectsAnAlreadyResolvedSuggestion(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider());
        $suggestion = $this->suggestion();
        $suggestion->setStatus(Status::Rejected);

        // Assert
        $this->expectException(SuggestionException::class);

        // Act
        $service->approve($suggestion, new stdClass(), $this->user(9));
    }

    public function testRejectResolvesWithoutCreatingARow(): void
    {
        // Arrange
        $provider = $this->provider(expectNoCreate: true);
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(Rejected::TYPE, self::isInstanceOf(User::class), self::anything());
        $service = $this->makeService(activity: $activity, provider: $provider);
        $suggestion = $this->suggestion();

        // Act
        $service->reject($suggestion, $this->user(9));

        // Assert
        self::assertSame(Status::Rejected, $suggestion->getStatus());
        self::assertNull($suggestion->getCreatedId());
    }

    public function testWithdrawByTheProposerMarksWithdrawnAndLogsNothing(): void
    {
        // Arrange
        $proposer = $this->user(5);
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::never())->method('log');
        $service = $this->makeService(activity: $activity, provider: $this->provider());
        $suggestion = $this->suggestion($proposer);

        // Act
        $service->withdraw($suggestion, $proposer);

        // Assert
        self::assertSame(Status::Withdrawn, $suggestion->getStatus());
        self::assertNull($suggestion->getReviewedBy());
    }

    public function testWithdrawByAnotherUserIsDenied(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider());

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $service->withdraw($this->suggestion($this->user(5)), $this->user(6));
    }

    public function testPendingReviewableByFiltersProviderAndPermission(): void
    {
        // Arrange
        $reviewable = $this->suggestion();
        $foreign = $this->suggestion();
        $foreign->setTargetType('other');

        $provider = $this->createStub(TargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $registry = $this->createStub(Registry::class);
        $registry->method('providerFor')->willReturnCallback(static fn(string $type): ?TargetProviderInterface => $type === 'location' ? $provider : null);
        $repo = $this->createStub(SuggestionRepository::class);
        $repo->method('findPending')->willReturn([$reviewable, $foreign]);

        $service = new SuggestionService($this->createStub(EntityManagerInterface::class), $repo, $registry, $this->createStub(ActivityService::class));

        // Act
        $result = $service->pendingReviewableBy($this->user(9));

        // Assert
        self::assertSame([$reviewable], $result);
    }

    public function testPendingForIsEmptyForATypeWithoutAnActiveProvider(): void
    {
        // Arrange
        $repo = $this->createMock(SuggestionRepository::class);
        $repo->expects(self::never())->method('findPendingByProposer');
        $service = $this->makeService(provider: null, repo: $repo);

        // Act
        $views = $service->pendingFor(5, 'location');

        // Assert
        self::assertSame([], $views);
    }

    public function testPendingForDescribesEachSuggestionThroughItsProvider(): void
    {
        // Arrange
        $suggestion = $this->suggestion();
        new ReflectionProperty(Suggestion::class, 'id')->setValue($suggestion, 3);
        $repo = $this->createStub(SuggestionRepository::class);
        $repo->method('findPendingByProposer')->willReturn([$suggestion]);
        $service = $this->makeService(provider: $this->provider(), repo: $repo);

        // Act
        $views = $service->pendingFor(5, 'location');

        // Assert
        self::assertCount(1, $views);
        self::assertSame(3, $views[0]->id);
        self::assertSame(Status::Pending, $views[0]->status);
        self::assertSame('Cafe in Berlin', $views[0]->description);
    }

    public function testFindHidesASuggestionWhoseProviderIsInactive(): void
    {
        // Arrange
        $repo = $this->createStub(SuggestionRepository::class);
        $repo->method('find')->willReturn($this->suggestion());
        $service = $this->makeService(provider: null, repo: $repo);

        // Act
        $view = $service->find(3);

        // Assert
        self::assertNull($view);
    }

    public function testRestoreStoresAPendingSuggestionWithoutActivity(): void
    {
        // Arrange
        $stored = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getReference')->willReturn($this->user(5));
        $em
            ->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$stored): void {
                $stored = $entity;
            });
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::never())->method('log');
        $service = $this->makeService($em, activity: $activity, provider: null);

        // Act
        $service->restore(new PortableSuggestion('glossary', 5, ['phrase' => 'x']));

        // Assert
        self::assertInstanceOf(Suggestion::class, $stored);
        self::assertSame('glossary', $stored->getTargetType());
        self::assertSame(Status::Pending, $stored->getStatus());
        self::assertSame(['phrase' => 'x'], $stored->getPayload());
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?ActivityService $activity = null,
        ?TargetProviderInterface $provider = null,
        ?SuggestionRepository $repo = null,
    ): SuggestionService {
        $registry = $this->createStub(Registry::class);
        $registry->method('providerFor')->willReturn($provider);
        $registry->method('has')->willReturn($provider !== null);

        return new SuggestionService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $repo ?? $this->createStub(SuggestionRepository::class),
            $registry,
            $activity ?? $this->createStub(ActivityService::class),
        );
    }

    private function provider(
        bool $canPropose = true,
        bool $canReview = true,
        ?string $validationError = null,
        bool $expectNoCreate = false,
    ): TargetProviderInterface&Stub {
        $provider = $expectNoCreate ? $this->createMock(TargetProviderInterface::class) : $this->createStub(TargetProviderInterface::class);
        if ($expectNoCreate) {
            $provider->expects(self::never())->method('create');
        }
        $provider->method('canPropose')->willReturn($canPropose);
        $provider->method('canReview')->willReturn($canReview);
        $provider->method('validate')->willReturn($validationError);
        $provider->method('toPayload')->willReturn(['name' => 'Cafe']);
        $provider->method('describe')->willReturn('Cafe in Berlin');

        return $provider;
    }

    private function suggestion(?User $proposer = null): Suggestion
    {
        $suggestion = new Suggestion();
        $suggestion->setTargetType('location');
        $suggestion->setProposedBy($proposer ?? $this->user(5));
        $suggestion->setPayload(['name' => 'Cafe']);

        return $suggestion;
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
