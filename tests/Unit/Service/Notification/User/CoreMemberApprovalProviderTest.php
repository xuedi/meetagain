<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification\User;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\Email\EmailService;
use App\Service\Notification\User\CoreMemberApprovalProvider;
use App\Service\Notification\User\ReviewNotificationItem;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CoreMemberApprovalProviderTest extends TestCase
{
    private function makeUser(int $id = 1, string $name = 'John', UserStatus $status = UserStatus::EmailVerified): User
    {
        $user = $this->createMock(User::class);
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
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByStatus')->willReturn($pendingUsers);
        $repo->method('find')->willReturn($findResult);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        return new CoreMemberApprovalProvider(
            userRepo: $repo,
            em: $this->createStub(EntityManagerInterface::class),
            emailService: $this->createStub(EmailService::class),
            activityService: $this->createStub(ActivityService::class),
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
}
