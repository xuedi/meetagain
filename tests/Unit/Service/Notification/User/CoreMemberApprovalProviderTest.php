<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Filter\Member\PendingApprovalFilterInterface;
use App\Filter\Member\PendingApprovalFilterService;
use App\Repository\UserRepository;
use App\Service\Member\UserService;
use App\Service\Notification\User\CoreMemberApprovalProvider;
use App\Service\Notification\User\ReviewNotificationItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CoreMemberApprovalProviderTest extends TestCase
{
    public function testGetReviewItemsReturnsOneItemPerPendingUser(): void
    {
        // Arrange
        $pendingUsers = [$this->makeUser(1, 'Alice'), $this->makeUser(2, 'Bob')];
        $provider = $this->makeProvider(pendingUsers: $pendingUsers);

        // Act
        $items = $provider->getReviewItems($this->reviewer(UserRole::Admin));

        // Assert
        static::assertCount(2, $items);
        static::assertInstanceOf(ReviewNotificationItem::class, $items[0]);
        static::assertSame('1', $items[0]->id);
        static::assertSame('2', $items[1]->id);
    }

    public function testGetReviewItemsReturnsEmptyForNonAdminWithoutAFilter(): void
    {
        // Arrange
        $provider = $this->makeProvider(pendingUsers: [$this->makeUser()]);

        // Act
        $items = $provider->getReviewItems($this->reviewer(UserRole::User));

        // Assert
        static::assertSame([], $items);
    }

    public function testAFilterHandsAPendingUserToANonAdminReviewer(): void
    {
        // Arrange
        $pending = $this->makeUser(1, 'Alice');
        $filter = $this->createStub(PendingApprovalFilterInterface::class);
        $filter->method('filterForReviewer')->willReturn([$pending]);
        $provider = $this->makeProvider(pendingUsers: [$pending], filters: [$filter]);

        // Act
        $items = $provider->getReviewItems($this->reviewer(UserRole::User));

        // Assert
        static::assertCount(1, $items);
    }

    public function testAFilterTakesAPendingUserAwayFromAnAdmin(): void
    {
        // Arrange
        $pending = $this->makeUser(1, 'Alice');
        $filter = $this->createStub(PendingApprovalFilterInterface::class);
        $filter->method('filterForReviewer')->willReturn([]);
        $provider = $this->makeProvider(pendingUsers: [$pending], findResult: $pending, filters: [$filter]);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->approveItem($this->reviewer(UserRole::Admin), '1');
    }

    public function testApproveItemThrowsForNonAdmin(): void
    {
        // Arrange
        $pending = $this->makeUser();
        $provider = $this->makeProvider(pendingUsers: [$pending], findResult: $pending);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->approveItem($this->reviewer(UserRole::User), '1');
    }

    public function testDenyItemThrowsForNonAdmin(): void
    {
        // Arrange
        $pending = $this->makeUser();
        $provider = $this->makeProvider(pendingUsers: [$pending], findResult: $pending);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->denyItem($this->reviewer(UserRole::User), '1');
    }

    public function testGetIdentifierIsStable(): void
    {
        static::assertSame('core.member_approval', $this->makeProvider()->getIdentifier());
    }

    public function testApproveItemThrowsWhenUserNotFound(): void
    {
        $provider = $this->makeProvider(findResult: null);

        $this->expectException(InvalidArgumentException::class);
        $provider->approveItem($this->reviewer(UserRole::Admin), '404');
    }

    public function testApproveItemThrowsWhenUserHasWrongStatus(): void
    {
        // Arrange
        $wrong = $this->makeUser(1, 'X', UserStatus::Active);
        $provider = $this->makeProvider(findResult: $wrong);

        // Act / Assert
        $this->expectException(InvalidArgumentException::class);
        $provider->approveItem($this->reviewer(UserRole::Admin), '1');
    }

    public function testApproveItemTransitionsPendingUserToActive(): void
    {
        // Arrange
        $pending = $this->makeUser(1, 'Pending', UserStatus::EmailVerified);
        $userService = $this->createMock(UserService::class);
        $userService->expects($this->once())->method('transitionStatus')->with(static::isInstanceOf(User::class), $pending, UserStatus::Active);
        $provider = $this->makeProvider(pendingUsers: [$pending], findResult: $pending, userService: $userService);

        // Act
        $provider->approveItem($this->reviewer(UserRole::Admin), '1');
    }

    public function testDenyItemThrowsWhenUserNotFound(): void
    {
        $provider = $this->makeProvider(findResult: null);

        $this->expectException(InvalidArgumentException::class);
        $provider->denyItem($this->reviewer(UserRole::Admin), '404');
    }

    public function testDenyItemThrowsWhenUserHasWrongStatus(): void
    {
        $wrong = $this->makeUser(1, 'X', UserStatus::Active);
        $provider = $this->makeProvider(findResult: $wrong);

        $this->expectException(InvalidArgumentException::class);
        $provider->denyItem($this->reviewer(UserRole::Admin), '1');
    }

    public function testDenyItemTransitionsPendingUserToDenied(): void
    {
        // Arrange
        $pending = $this->makeUser(1, 'Pending', UserStatus::EmailVerified);
        $userService = $this->createMock(UserService::class);
        $userService->expects($this->once())->method('transitionStatus')->with(static::isInstanceOf(User::class), $pending, UserStatus::Denied);
        $provider = $this->makeProvider(pendingUsers: [$pending], findResult: $pending, userService: $userService);

        // Act
        $provider->denyItem($this->reviewer(UserRole::Admin), '1');
    }

    private function makeUser(int $id = 1, string $name = 'John', UserStatus $status = UserStatus::EmailVerified): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getName')->willReturn($name);
        $user->method('getStatus')->willReturn($status);

        return $user;
    }

    private function reviewer(UserRole $role): User
    {
        $reviewer = $this->createStub(User::class);
        $reviewer->method('getId')->willReturn(99);
        $reviewer->method('getRole')->willReturn($role);

        return $reviewer;
    }

    /**
     * @param list<User> $pendingUsers
     * @param list<PendingApprovalFilterInterface> $filters
     */
    private function makeProvider(
        array $pendingUsers = [],
        ?User $findResult = null,
        array $filters = [],
        ?UserService $userService = null,
    ): CoreMemberApprovalProvider {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByStatus')->willReturn($pendingUsers);
        $repo->method('find')->willReturn($findResult);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CoreMemberApprovalProvider(
            userRepo: $repo,
            userService: $userService ?? $this->createStub(UserService::class),
            pendingApproval: new PendingApprovalFilterService($filters, $repo),
            translator: $translator,
        );
    }
}
