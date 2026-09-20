<?php declare(strict_types=1);

namespace Tests\Unit\Suggestion;

use App\Activity\ActivityService;
use App\Activity\Messages\SuggestionApproved;
use App\Activity\Messages\SuggestionCreated;
use App\Activity\Messages\SuggestionRejected;
use App\Entity\Suggestion;
use App\Entity\User;
use App\Enum\SuggestionStatus;
use App\Repository\SuggestionRepository;
use App\Suggestion\SuggestionException;
use App\Suggestion\SuggestionRegistry;
use App\Suggestion\SuggestionService;
use App\Suggestion\SuggestionTargetProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
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
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(SuggestionCreated::TYPE, self::isInstanceOf(User::class), self::arrayHasKey('description'));
        $service = $this->makeService($em, activity: $activity, provider: $this->provider());

        // Act
        $suggestion = $service->propose('location', $this->user(5), new stdClass());

        // Assert
        self::assertSame('location', $suggestion->getTargetType());
        self::assertSame(SuggestionStatus::Pending, $suggestion->getStatus());
        self::assertSame(['name' => 'Cafe'], $suggestion->getPayload());
    }

    public function testProposeIsDeniedWithoutPermission(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider(canPropose: false));

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $service->propose('location', $this->user(5), new stdClass());
    }

    public function testProposeRejectsAnInvalidDraft(): void
    {
        // Arrange
        $service = $this->makeService(provider: $this->provider(validationError: 'that venue already exists'));

        // Assert
        $this->expectException(SuggestionException::class);
        $this->expectExceptionMessage('that venue already exists');

        // Act
        $service->propose('location', $this->user(5), new stdClass());
    }

    public function testProposeThrowsForAnUnregisteredTargetType(): void
    {
        // Arrange
        $service = $this->makeService(provider: null);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $service->propose('unknown', $this->user(5), new stdClass());
    }

    public function testApproveCreatesTheRowAndRecordsItsId(): void
    {
        // Arrange
        $provider = $this->provider();
        $provider->method('create')->willReturn(42);
        $activity = $this->createMock(ActivityService::class);
        $activity->expects(self::once())->method('log')->with(SuggestionApproved::TYPE, self::isInstanceOf(User::class), self::anything());
        $service = $this->makeService(activity: $activity, provider: $provider);
        $suggestion = $this->suggestion();
        $reviewer = $this->user(9);

        // Act
        $createdId = $service->approve($suggestion, new stdClass(), $reviewer);

        // Assert
        self::assertSame(42, $createdId);
        self::assertSame(42, $suggestion->getCreatedId());
        self::assertSame(SuggestionStatus::Approved, $suggestion->getStatus());
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
        $suggestion->setStatus(SuggestionStatus::Rejected);

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
        $activity->expects(self::once())->method('log')->with(SuggestionRejected::TYPE, self::isInstanceOf(User::class), self::anything());
        $service = $this->makeService(activity: $activity, provider: $provider);
        $suggestion = $this->suggestion();

        // Act
        $service->reject($suggestion, $this->user(9));

        // Assert
        self::assertSame(SuggestionStatus::Rejected, $suggestion->getStatus());
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
        self::assertSame(SuggestionStatus::Withdrawn, $suggestion->getStatus());
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

        $provider = $this->createStub(SuggestionTargetProviderInterface::class);
        $provider->method('canReview')->willReturn(true);
        $registry = $this->createStub(SuggestionRegistry::class);
        $registry->method('providerFor')->willReturnCallback(static fn(string $type): ?SuggestionTargetProviderInterface => $type === 'location'
            ? $provider
            : null);
        $repo = $this->createStub(SuggestionRepository::class);
        $repo->method('findPending')->willReturn([$reviewable, $foreign]);

        $service = new SuggestionService($this->createStub(EntityManagerInterface::class), $repo, $registry, $this->createStub(ActivityService::class));

        // Act
        $result = $service->pendingReviewableBy($this->user(9));

        // Assert
        self::assertSame([$reviewable], $result);
    }

    private function makeService(
        ?EntityManagerInterface $em = null,
        ?ActivityService $activity = null,
        ?SuggestionTargetProviderInterface $provider = null,
    ): SuggestionService {
        $registry = $this->createStub(SuggestionRegistry::class);
        $registry->method('providerFor')->willReturn($provider);
        $registry->method('has')->willReturn($provider !== null);

        return new SuggestionService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(SuggestionRepository::class),
            $registry,
            $activity ?? $this->createStub(ActivityService::class),
        );
    }

    private function provider(
        bool $canPropose = true,
        bool $canReview = true,
        ?string $validationError = null,
        bool $expectNoCreate = false,
    ): SuggestionTargetProviderInterface&Stub {
        $provider = $expectNoCreate ? $this->createMock(SuggestionTargetProviderInterface::class) : $this->createStub(SuggestionTargetProviderInterface::class);
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
