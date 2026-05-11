<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\Member\UserService;
use App\Service\Notification\User\CoreMemberApprovalProvider;
use App\Service\Notification\User\ReviewNotificationItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CoreMemberApprovalProviderTest extends TestCase
{
    private function makeUser(int $id = 1, string $name = 'John', UserStatus $status = UserStatus::EmailVerified): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getName')->willReturn($name);
        $user->method('getStatus')->willReturn($status);

        return $user;
    }

    private function makeProvider(
        array $pendingUsers = [],
        bool $isAdmin = true,
        ?User $findResult = null,
    ): CoreMemberApprovalProvider {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByStatus')->willReturn($pendingUsers);
        $repo->method('find')->willReturn($findResult);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        return new CoreMemberApprovalProvider(
            userRepo: $repo,
            userService: $this->createStub(UserService::class),
            security: $security,
        );
    }

    public function testGetReviewItemsReturnsOneItemPerPendingUser(): void
    {
        // Arrange
        $admin = $this->createStub(User::class);
        $pendingUsers = [$this->makeUser(1, 'Alice'), $this->makeUser(2, 'Bob')];
        $provider = $this->makeProvider(pendingUsers: $pendingUsers);

        // Act
        $items = $provider->getReviewItems($admin);

        // Assert
        static::assertCount(2, $items);
        static::assertInstanceOf(ReviewNotificationItem::class, $items[0]);
        static::assertSame('1', $items[0]->id);
        static::assertSame('2', $items[1]->id);
    }

    public function testGetReviewItemsReturnsEmptyForNonAdmin(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(pendingUsers: [$this->makeUser()], isAdmin: false);

        // Act
        $items = $provider->getReviewItems($user);

        // Assert
        static::assertSame([], $items);
    }

    public function testApproveItemThrowsForNonAdmin(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(isAdmin: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->approveItem($user, '1');
    }

    public function testDenyItemThrowsForNonAdmin(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $provider = $this->makeProvider(isAdmin: false);

        // Assert
        $this->expectException(AccessDeniedException::class);

        // Act
        $provider->denyItem($user, '1');
    }

    public function testGetIdentifierIsStable(): void
    {
        static::assertSame('core.member_approval', $this->makeProvider()->getIdentifier());
    }

    public function testApproveItemThrowsWhenUserNotFound(): void
    {
        $provider = $this->makeProvider(findResult: null, isAdmin: true);

        $this->expectException(InvalidArgumentException::class);
        $provider->approveItem($this->createStub(User::class), '404');
    }

    public function testApproveItemThrowsWhenUserHasWrongStatus(): void
    {
        // Arrange - user exists but is already Active, not EmailVerified
        $wrong = $this->makeUser(1, 'X', UserStatus::Active);
        $provider = $this->makeProvider(findResult: $wrong, isAdmin: true);

        // Act / Assert
        $this->expectException(InvalidArgumentException::class);
        $provider->approveItem($this->createStub(User::class), '1');
    }

    public function testApproveItemTransitionsPendingUserToActive(): void
    {
        // Arrange
        $pending = $this->makeUser(1, 'Pending', UserStatus::EmailVerified);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('find')->willReturn($pending);

        $userService = $this->createMock(UserService::class);
        $userService->expects($this->once())
            ->method('transitionStatus')
            ->with(static::isInstanceOf(User::class), $pending, UserStatus::Active);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $provider = new CoreMemberApprovalProvider($userRepo, $userService, $security);

        // Act
        $provider->approveItem($this->createStub(User::class), '1');
    }

    public function testDenyItemThrowsWhenUserNotFound(): void
    {
        $provider = $this->makeProvider(findResult: null, isAdmin: true);

        $this->expectException(InvalidArgumentException::class);
        $provider->denyItem($this->createStub(User::class), '404');
    }

    public function testDenyItemThrowsWhenUserHasWrongStatus(): void
    {
        $wrong = $this->makeUser(1, 'X', UserStatus::Active);
        $provider = $this->makeProvider(findResult: $wrong, isAdmin: true);

        $this->expectException(InvalidArgumentException::class);
        $provider->denyItem($this->createStub(User::class), '1');
    }

    public function testDenyItemTransitionsPendingUserToDenied(): void
    {
        // Arrange
        $pending = $this->makeUser(1, 'Pending', UserStatus::EmailVerified);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('find')->willReturn($pending);

        $userService = $this->createMock(UserService::class);
        $userService->expects($this->once())
            ->method('transitionStatus')
            ->with(static::isInstanceOf(User::class), $pending, UserStatus::Denied);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);

        $provider = new CoreMemberApprovalProvider($userRepo, $userService, $security);

        // Act
        $provider->denyItem($this->createStub(User::class), '1');
    }
}
