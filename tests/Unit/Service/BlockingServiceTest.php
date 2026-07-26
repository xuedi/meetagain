<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Activity\ActivityService;
use App\Activity\Messages\BlockedUser;
use App\Activity\Messages\UnblockedUser;
use App\Entity\User;
use App\Entity\UserBlock;
use App\Repository\UserBlockRepository;
use App\Service\Member\BlockingService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BlockingServiceTest extends TestCase
{
    public function testBlockThrowsExceptionWhenBlockingYourself(): void
    {
        // Arrange
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(42);

        $subject = new BlockingService(
            blockRepo: $this->createStub(UserBlockRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot block yourself');

        // Act
        $subject->block($user, $user);
    }

    public function testBlockDoesNothingWhenAlreadyBlocked(): void
    {
        // Arrange
        $blocker = $this->createStub(User::class);
        $blocker->method('getId')->willReturn(1);

        $blocked = $this->createStub(User::class);
        $blocked->method('getId')->willReturn(2);

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('isBlocked')->with($blocker, $blocked)->willReturn(true);

        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->never())->method('persist');
        $emMock->expects($this->never())->method('flush');

        $subject = new BlockingService(blockRepo: $blockRepoMock, em: $emMock, activityService: $this->createStub(ActivityService::class));

        // Act
        $subject->block($blocker, $blocked);

        // Assert
    }

    public function testBlockCreatesBlockAndRemovesFollowing(): void
    {
        // Arrange
        $blocker = $this->createMock(User::class);
        $blocker->method('getId')->willReturn(1);
        $blocker->expects($this->once())->method('removeFollowing');
        $blocker->expects($this->once())->method('removeFollower');

        $blocked = $this->createMock(User::class);
        $blocked->method('getId')->willReturn(2);
        $blocked->expects($this->once())->method('removeFollowing');
        $blocked->expects($this->once())->method('removeFollower');

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('isBlocked')->with($blocker, $blocked)->willReturn(false);

        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock
            ->expects($this->exactly(3))
            ->method('persist')
            ->with(static::logicalOr(static::isInstanceOf(UserBlock::class), static::identicalTo($blocker), static::identicalTo($blocked)));
        $emMock->expects($this->once())->method('flush');

        // Arrange
        $activityServiceMock = $this->createMock(ActivityService::class);
        $activityServiceMock->expects($this->once())->method('log')->with(BlockedUser::TYPE, $blocker, [
            'user_id' => 2,
        ]);

        $subject = new BlockingService(blockRepo: $blockRepoMock, em: $emMock, activityService: $activityServiceMock);

        // Act
        $subject->block($blocker, $blocked);

        // Assert
    }

    public function testUnblockDoesNothingWhenNotBlocked(): void
    {
        // Arrange
        $blocker = $this->createStub(User::class);
        $blocked = $this->createStub(User::class);

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('findOneBy')->with(['blocker' => $blocker, 'blocked' => $blocked])->willReturn(null);

        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->never())->method('remove');
        $emMock->expects($this->never())->method('flush');

        $subject = new BlockingService(blockRepo: $blockRepoMock, em: $emMock, activityService: $this->createStub(ActivityService::class));

        // Act
        $subject->unblock($blocker, $blocked);

        // Assert
    }

    public function testUnblockRemovesBlockAndLogsActivity(): void
    {
        // Arrange
        $blocker = $this->createStub(User::class);
        $blocked = $this->createStub(User::class);
        $blocked->method('getId')->willReturn(42);

        // Arrange
        $block = $this->createStub(UserBlock::class);

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('findOneBy')->with(['blocker' => $blocker, 'blocked' => $blocked])->willReturn($block);

        // Arrange
        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->expects($this->once())->method('remove')->with($block);
        $emMock->expects($this->once())->method('flush');

        // Arrange
        $activityServiceMock = $this->createMock(ActivityService::class);
        $activityServiceMock->expects($this->once())->method('log')->with(UnblockedUser::TYPE, $blocker, [
            'user_id' => 42,
        ]);

        $subject = new BlockingService(blockRepo: $blockRepoMock, em: $emMock, activityService: $activityServiceMock);

        // Act
        $subject->unblock($blocker, $blocked);

        // Assert
    }

    public function testIsBlockedDelegatesToRepository(): void
    {
        // Arrange
        $user1 = $this->createStub(User::class);
        $user2 = $this->createStub(User::class);

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('isBlockedEitherWay')->with($user1, $user2)->willReturn(true);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert
        static::assertTrue($subject->isBlocked($user1, $user2));
    }

    public function testHasBlockedDelegatesToRepository(): void
    {
        // Arrange
        $blocker = $this->createStub(User::class);
        $blocked = $this->createStub(User::class);

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('isBlocked')->with($blocker, $blocked)->willReturn(true);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert
        static::assertTrue($subject->hasBlocked($blocker, $blocked));
    }

    public function testCanInteractReturnsTrueWhenNotBlocked(): void
    {
        // Arrange
        $actor = $this->createStub(User::class);
        $target = $this->createStub(User::class);

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('isBlockedEitherWay')->with($actor, $target)->willReturn(false);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert
        static::assertTrue($subject->canInteract($actor, $target));
    }

    public function testCanInteractReturnsFalseWhenBlocked(): void
    {
        // Arrange
        $actor = $this->createStub(User::class);
        $target = $this->createStub(User::class);

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('isBlockedEitherWay')->with($actor, $target)->willReturn(true);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert
        static::assertFalse($subject->canInteract($actor, $target));
    }

    public function testGetBlockedUsersDelegatesToRepository(): void
    {
        // Arrange
        $user = $this->createStub(User::class);

        // Arrange
        $block1 = $this->createStub(UserBlock::class);
        $block2 = $this->createStub(UserBlock::class);
        $expectedBlocks = [$block1, $block2];

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('getBlockedUsers')->with($user)->willReturn($expectedBlocks);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert
        static::assertSame($expectedBlocks, $subject->getBlockedUsers($user));
    }

    public function testGetExcludedUserIdsDelegatesToRepository(): void
    {
        // Arrange
        $user = $this->createStub(User::class);

        // Arrange
        $expectedIds = [1, 2, 3];

        // Arrange
        $blockRepoMock = $this->createMock(UserBlockRepository::class);
        $blockRepoMock->expects($this->once())->method('getAllBlockRelatedIds')->with($user)->willReturn($expectedIds);

        $subject = new BlockingService(
            blockRepo: $blockRepoMock,
            em: $this->createStub(EntityManagerInterface::class),
            activityService: $this->createStub(ActivityService::class),
        );

        // Act & Assert
        static::assertSame($expectedIds, $subject->getExcludedUserIds($user));
    }
}
