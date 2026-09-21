<?php declare(strict_types=1);

namespace Tests\Unit\Service\Notification;

use App\Entity\User;
use App\Enum\UserRole;
use App\Filter\Member\PendingApprovalFilterService;
use App\Repository\UserRepository;
use App\Service\Notification\Admin\UserPendingApprovalNotificationProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;

class UserPendingApprovalNotificationProviderTest extends TestCase
{
    public function testGetSectionReturnsExpectedString(): void
    {
        // Arrange
        $provider = $this->provider($this->createStub(UserRepository::class));

        // Act
        $section = $provider->getSection();

        // Assert
        static::assertInstanceOf(TranslatableMessage::class, $section);
        static::assertSame('notifications.section_pending_approval', $section->getMessage());
    }

    public function testGetPendingItemsWithEmptyUserListReturnsEmptyArray(): void
    {
        // Arrange
        $repoStub = $this->createStub(UserRepository::class);
        $repoStub->method('findByStatus')->willReturn([]);

        $provider = $this->provider($repoStub);

        // Act & Assert
        static::assertSame([], $provider->getPendingItems($this->admin()));
    }

    public function testGetPendingItemsWithOneUserReturnsItemWithNameAndEmail(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $user->method('getName')->willReturn('Alice');
        $user->method('getEmail')->willReturn('alice@example.com');

        $repoStub = $this->createStub(UserRepository::class);
        $repoStub->method('findByStatus')->willReturn([$user]);

        $provider = $this->provider($repoStub);

        // Act
        $items = $provider->getPendingItems($this->admin());

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

        $provider = $this->provider($repoStub);

        // Act & Assert
        static::assertCount(2, $provider->getPendingItems($this->admin()));
    }

    public function testGetLatestPendingAtDelegatesToRepo(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2025-10-01');

        $repoMock = $this->createMock(UserRepository::class);
        $repoMock->expects($this->once())->method('getLatestPendingCreatedAt')->willReturn($date);

        $provider = $this->provider($repoMock);

        // Act & Assert
        static::assertSame($date, $provider->getLatestPendingAt());
    }

    private function provider(UserRepository $repository): UserPendingApprovalNotificationProvider
    {
        return new UserPendingApprovalNotificationProvider($repository, new PendingApprovalFilterService([], $repository));
    }

    private function admin(): User
    {
        $admin = $this->createStub(User::class);
        $admin->method('getRole')->willReturn(UserRole::Admin);

        return $admin;
    }
}
