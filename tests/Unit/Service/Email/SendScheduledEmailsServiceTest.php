<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Emails\DueContext;
use App\Emails\ScheduledEmailInterface;
use App\Service\Email\SendScheduledEmailsService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Unit\Stubs\UserStub;

final class SendScheduledEmailsServiceTest extends TestCase
{
    public function testSkipsOutsideAllowedHours(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 06:00:00'));
        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->expects($this->never())->method('getDueContexts');

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger());

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertStringContainsString('outside allowed hours', $result->message);
    }

    public function testSkipsAtOrAfter22(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 22:00:00'));
        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->expects($this->never())->method('getDueContexts');

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger());

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertStringContainsString('outside allowed hours', $result->message);
    }

    public function testReturnsZeroWhenNoDueContexts(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));
        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getDueContexts')->willReturn([]);

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger());

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame('0 emails queued', $result->message);
    }

    public function testGuardCheckGatesEachRecipient(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);

        $dueContext = new DueContext(['event' => 'mock'], [$user1, $user2]);

        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('guardCheck')->willReturnCallback(static fn(array $ctx) => $ctx['user'] === $user1);
        $email->expects($this->once())->method('send');
        $email->expects($this->once())->method('markContextSent')->with($dueContext);

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger());

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame('1 emails queued', $result->message);
    }

    public function testMarkContextSentCalledAfterAllRecipientsProcessed(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);

        $dueContext = new DueContext([], [$user1, $user2]);

        $callOrder = [];

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('guardCheck')->willReturn(true);
        $email
            ->method('send')
            ->willReturnCallback(static function () use (&$callOrder) {
                $callOrder[] = 'send';
            });
        $email
            ->method('markContextSent')
            ->willReturnCallback(static function () use (&$callOrder) {
                $callOrder[] = 'markContextSent';
            });

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger());

        // Act
        $output = new BufferedOutput();
        $service->runCronTask($output);

        // Assert: markContextSent called after all send calls
        static::assertSame(['send', 'send', 'markContextSent'], $callOrder);
    }

    public function testTotalSentCountAcrossMultipleEmailTypes(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user = new UserStub()->setId(1);
        $ctx1 = new DueContext([], [$user]);
        $ctx2 = new DueContext([], [$user, new UserStub()->setId(2)]);

        $email1 = $this->createStub(ScheduledEmailInterface::class);
        $email1->method('getDueContexts')->willReturn([$ctx1]);
        $email1->method('guardCheck')->willReturn(true);

        $email2 = $this->createStub(ScheduledEmailInterface::class);
        $email2->method('getDueContexts')->willReturn([$ctx2]);
        $email2->method('guardCheck')->willReturn(true);

        $service = new SendScheduledEmailsService([$email1, $email2], $clock, new NullLogger());

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert: 1 from email1 + 2 from email2
        static::assertSame('3 emails queued', $result->message);
    }

    public function testGuardCheckExceptionIsCaughtLoggedAndLoopContinues(): void
    {
        // Arrange: user1 throws, user2 sends
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);
        $dueContext = new DueContext([], [$user1, $user2]);

        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('guardCheck')->willReturnCallback(static function (array $ctx) use ($user1): bool {
            if ($ctx['user'] === $user1) {
                throw new InvalidArgumentException('missing recipient');
            }
            return true;
        });
        $email->expects($this->once())->method('send'); // only user2
        $email->expects($this->once())->method('markContextSent');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with('guardCheck contract violation - email skipped', $this->anything());

        $service = new SendScheduledEmailsService([$email], $clock, $logger);

        // Act
        $result = $service->runCronTask(new BufferedOutput());

        // Assert
        static::assertSame('1 emails queued', $result->message);
    }

    public function testGuardCheckExceptionDedupedPerSweep(): void
    {
        // Arrange: 3 users, same throw message - expect 1 log line only
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $dueContext = new DueContext([], [
            new UserStub()->setId(1),
            new UserStub()->setId(2),
            new UserStub()->setId(3),
        ]);

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('guardCheck')->willThrowException(new InvalidArgumentException('same message'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new SendScheduledEmailsService([$email], $clock, $logger);

        // Act
        $service->runCronTask(new BufferedOutput());
    }

    public function testGuardCheckExceptionDistinctMessagesAreAllLogged(): void
    {
        // Arrange: 2 users, 2 distinct throw messages - expect 2 log lines
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);
        $dueContext = new DueContext([], [$user1, $user2]);

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('guardCheck')->willReturnCallback(
            static fn(array $ctx): bool => throw new InvalidArgumentException(
                $ctx['user'] === $user1 ? 'missing recipient' : 'missing event',
            ),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $service = new SendScheduledEmailsService([$email], $clock, $logger);

        // Act
        $service->runCronTask(new BufferedOutput());
    }
}
