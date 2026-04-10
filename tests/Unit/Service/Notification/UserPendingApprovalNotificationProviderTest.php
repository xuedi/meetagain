<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Notification\Admin\UserPendingApprovalNotificationProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UserPendingApprovalNotificationProviderTest extends TestCase
{
    public function testGetSectionReturnsExpectedString(): void
    {
        // Arrange
        $provider = new UserPendingApprovalNotificationProvider(
            userRepository: $this->createStub(UserRepository::class),
        );

        // Act & Assert
        static::assertSame('Users Pending Approval', $provider->getSection());
    }

    public function testGetPendingItemsWithEmptyUserListReturnsEmptyArray(): void
    {
        // Arrange
        $repoStub = $this->createStub(UserRepository::class);
        $repoStub->method('findByStatus')->willReturn([]);

        $provider = new UserPendingApprovalNotificationProvider(userRepository: $repoStub);

        // Act & Assert
        static::assertSame([], $provider->getPendingItems());
    }

    public function testGetPendingItemsWithOneUserReturnsItemWithNameAndEmail(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $user->method('getName')->willReturn('Alice');
        $user->method('getEmail')->willReturn('alice@example.com');

        $repoStub = $this->createStub(UserRepository::class);
        $repoStub->method('findByStatus')->willReturn([$user]);

        $provider = new UserPendingApprovalNotificationProvider(userRepository: $repoStub);

        // Act
        $items = $provider->getPendingItems();

        // Assert
        static::assertCount(1, $items);
        static::assertStringContainsString('Alice', $items[0]->label);
        static::assertStringContainsString('alice@example.com', $items[0]->label);
    }

    public function testGetPendingItemsWithTwoUsersReturnsTwoItems(): void
    {
        // Arrange
        $user1 = $this->createStub(User::class);
        $user1->method('getName')->willReturn('Alice');
        $user1->method('getEmail')->willReturn('alice@example.com');

        $user2 = $this->createStub(User::class);
        $user2->method('getName')->willReturn('Bob');
        $user2->method('getEmail')->willReturn('bob@example.com');

        $repoStub = $this->createStub(UserRepository::class);
        $repoStub->method('findByStatus')->willReturn([$user1, $user2]);

        $provider = new UserPendingApprovalNotificationProvider(userRepository: $repoStub);

        // Act & Assert
        static::assertCount(2, $provider->getPendingItems());
    }

    public function testGetLatestPendingAtDelegatesToRepo(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-10-01');

        $repoMock = $this->createMock(UserRepository::class);
        $repoMock->expects($this->once())->method('getLatestPendingCreatedAt')->willReturn($date);

        $provider = new UserPendingApprovalNotificationProvider(userRepository: $repoMock);

        // Act & Assert
        static::assertSame($date, $provider->getLatestPendingAt());
    }
}
