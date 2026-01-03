<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\ActivityType;
use App\Entity\User;
use App\Entity\UserBlock;
use App\Repository\UserBlockRepository;
use App\Service\ActivityService;
use App\Service\BlockingService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BlockingServiceTest extends TestCase
{
    public function testBlockThrowsExceptionWhenBlockingYourself(): void
    {
        // Arrange: create user that tries to block themselves
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(42);

        $subject = new BlockingService(
            blockRepo: $this->createStub(UserBlockRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Assert: expect exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot block yourself');

        // Act: try to block yourself
        $subject->block($user, $user);
    }

    public function testBlockDoesNothingWhenAlreadyBlocked(): void
    {
        // Arrange: create users
        $blocker = $this->createStub(User::class);
        $blocker->method('getId')->willReturn(1);

        $blocked = $this->createStub(User::class);
        $blocked->method('getId')->willReturn(2);

        // Arrange: repository indicates already blocked
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('isBlocked')
            ->with($blocker, $blocked)
            ->willReturn(true);

        // Arrange: entity manager should not be called
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->never())->method('persist');
        $emMock->expects($this->never())->method('flush');

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $emMock,
            activityService: $this->createStub(ActivityService::class),
        );

        // Act: try to block already blocked user
        $subject->block($blocker, $blocked);

        // Assert: expectations verified by PHPUnit
    }

    public function testBlockCreatesBlockAndRemovesFollowing(): void
    {
        // Arrange: create users
        $blocker = $this->createMock(User::class);
        $blocker->method('getId')->willReturn(1);
        $blocker->expects($this->once())->method('removeFollowing');
        $blocker->expects($this->once())->method('removeFollower');

        $blocked = $this->createMock(User::class);
        $blocked->method('getId')->willReturn(2);
        $blocked->expects($this->once())->method('removeFollowing');
        $blocked->expects($this->once())->method('removeFollower');

        // Arrange: repository indicates not blocked
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('isBlocked')
            ->with($blocker, $blocked)
            ->willReturn(false);

        // Arrange: entity manager should persist block and both users
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->exactly(3))
            ->method('persist')
            ->with($this->logicalOr(
                $this->isInstanceOf(UserBlock::class),
                $this->identicalTo($blocker),
                $this->identicalTo($blocked)
            ));
        $emMock->expects($this->once())->method('flush');

        // Arrange: activity service should log
        $activityServiceMock = $this->createMock(ActivityService::class);
        $activityServiceMock
            ->expects($this->once())
            ->method('log')
            ->with(
                ActivityType::BlockedUser,
                $blocker,
                ['user_id' => 2]
            );

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $emMock,
            activityService: $activityServiceMock,
        );

        // Act: block user
        $subject->block($blocker, $blocked);

        // Assert: expectations verified by PHPUnit
    }

    public function testUnblockDoesNothingWhenNotBlocked(): void
    {
        // Arrange: create users
        $blocker = $this->createStub(User::class);
        $blocked = $this->createStub(User::class);

        // Arrange: repository returns null (not blocked)
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['blocker' => $blocker, 'blocked' => $blocked])
            ->willReturn(null);

        // Arrange: entity manager should not be called
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->never())->method('remove');
        $emMock->expects($this->never())->method('flush');

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $emMock,
            activityService: $this->createStub(ActivityService::class),
        );

        // Act: try to unblock not blocked user
        $subject->unblock($blocker, $blocked);

        // Assert: expectations verified by PHPUnit
    }

    public function testUnblockRemovesBlockAndLogsActivity(): void
    {
        // Arrange: create users
        $blocker = $this->createStub(User::class);
        $blocked = $this->createStub(User::class);
        $blocked->method('getId')->willReturn(42);

        // Arrange: create existing block
        $block = $this->createStub(UserBlock::class);

        // Arrange: repository returns block
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['blocker' => $blocker, 'blocked' => $blocked])
            ->willReturn($block);

        // Arrange: entity manager should remove and flush
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('remove')->with($block);
        $emMock->expects($this->once())->method('flush');

        // Arrange: activity service should log
        $activityServiceMock = $this->createMock(ActivityService::class);
        $activityServiceMock
            ->expects($this->once())
            ->method('log')
            ->with(
                ActivityType::UnblockedUser,
                $blocker,
                ['user_id' => 42]
            );

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $emMock,
            activityService: $activityServiceMock,
        );

        // Act: unblock user
        $subject->unblock($blocker, $blocked);

        // Assert: expectations verified by PHPUnit
    }

    public function testIsBlockedDelegatesToRepository(): void
    {
        // Arrange: create users
        $user1 = $this->createStub(User::class);
        $user2 = $this->createStub(User::class);

        // Arrange: repository returns true
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('isBlockedEitherWay')
            ->with($user1, $user2)
            ->willReturn(true);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert: check if blocked
        $this->assertTrue($subject->isBlocked($user1, $user2));
    }

    public function testHasBlockedDelegatesToRepository(): void
    {
        // Arrange: create users
        $blocker = $this->createStub(User::class);
        $blocked = $this->createStub(User::class);

        // Arrange: repository returns true
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('isBlocked')
            ->with($blocker, $blocked)
            ->willReturn(true);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert: check if has blocked
        $this->assertTrue($subject->hasBlocked($blocker, $blocked));
    }

    public function testCanInteractReturnsTrueWhenNotBlocked(): void
    {
        // Arrange: create users
        $actor = $this->createStub(User::class);
        $target = $this->createStub(User::class);

        // Arrange: repository returns false (not blocked)
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('isBlockedEitherWay')
            ->with($actor, $target)
            ->willReturn(false);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert: can interact
        $this->assertTrue($subject->canInteract($actor, $target));
    }

    public function testCanInteractReturnsFalseWhenBlocked(): void
    {
        // Arrange: create users
        $actor = $this->createStub(User::class);
        $target = $this->createStub(User::class);

        // Arrange: repository returns true (blocked)
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('isBlockedEitherWay')
            ->with($actor, $target)
            ->willReturn(true);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert: cannot interact
        $this->assertFalse($subject->canInteract($actor, $target));
    }

    public function testGetBlockedUsersDelegatesToRepository(): void
    {
        // Arrange: create user
        $user = $this->createStub(User::class);

        // Arrange: create expected blocks
        $block1 = $this->createStub(UserBlock::class);
        $block2 = $this->createStub(UserBlock::class);
        $expectedBlocks = [$block1, $block2];

        // Arrange: repository returns blocks
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('getBlockedUsers')
            ->with($user)
            ->willReturn($expectedBlocks);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert: get blocked users
        $this->assertSame($expectedBlocks, $subject->getBlockedUsers($user));
    }

    public function testGetExcludedUserIdsDelegatesToRepository(): void
    {
        // Arrange: create user
        $user = $this->createStub(User::class);

        // Arrange: expected IDs
        $expectedIds = [1, 2, 3];

        // Arrange: repository returns IDs
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock
            ->expects($this->once())
            ->method('getAllBlockRelatedIds')
            ->with($user)
            ->willReturn($expectedIds);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert: get excluded user IDs
        $this->assertSame($expectedIds, $subject->getExcludedUserIds($user));
    }
}
