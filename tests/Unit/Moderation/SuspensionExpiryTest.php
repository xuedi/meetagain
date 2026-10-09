<?php declare(strict_types=1);

namespace Tests\Unit\Moderation;

use App\Enum\CronTaskStatus;
use App\Enum\UserStatus;
use App\Moderation\SuspensionExpiry;
use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use App\Service\Member\UserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Unit\Stubs\UserStub;

class SuspensionExpiryTest extends TestCase
{
    public function testEveryExpiredSuspensionIsLiftedByTheSystemUser(): void
    {
        // Arrange
        $system = new UserStub()->setId(1);
        $first = new UserStub()->setId(2);
        $second = new UserStub()->setId(3);
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findExpiredSuspensions')->willReturn([$first, $second]);
        $repository->method('find')->willReturn($system);
        $lifted = [];
        $userService = $this->createMock(UserService::class);
        $userService
            ->expects(self::exactly(2))
            ->method('transitionStatus')
            ->willReturnCallback(static function ($actor, $target, UserStatus $status) use ($system, &$lifted): void {
                static::assertSame($system, $actor);
                static::assertSame(UserStatus::Active, $status);
                $lifted[] = $target;
            });
        $task = $this->makeTask($repository, $userService);

        // Act
        $result = $task->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('2 lifted', $result->message);
        static::assertSame([$first, $second], $lifted);
    }

    public function testNothingExpiredTouchesNobody(): void
    {
        // Arrange
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findExpiredSuspensions')->willReturn([]);
        $userService = $this->createMock(UserService::class);
        $userService->expects(self::never())->method('transitionStatus');
        $task = $this->makeTask($repository, $userService);

        // Act
        $result = $task->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('0 lifted', $result->message);
    }

    public function testAMissingSystemUserIsAnError(): void
    {
        // Arrange
        $repository = $this->createStub(UserRepository::class);
        $repository->method('findExpiredSuspensions')->willReturn([new UserStub()->setId(2)]);
        $repository->method('find')->willReturn(null);
        $task = $this->makeTask($repository, $this->createStub(UserService::class));

        // Act
        $result = $task->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::error, $result->status);
    }

    private function makeTask(UserRepository $repository, UserService $userService): SuspensionExpiry
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('getSystemUserId')->willReturn(1);

        return new SuspensionExpiry($repository, $userService, $config, new MockClock('2026-10-09 12:00'));
    }
}
