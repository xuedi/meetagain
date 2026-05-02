<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Emails\DueContext;
use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Emails\Guard\EmailGuardEvaluator;
use App\Emails\ScheduledEmailInterface;
use App\Service\Email\SendScheduledEmailsService;
use DateTimeImmutable;
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

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), new EmailGuardEvaluator());

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

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), new EmailGuardEvaluator());

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

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), new EmailGuardEvaluator());

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame('0 emails queued', $result->message);
    }

    public function testGuardChainGatesEachRecipient(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);

        $dueContext = new DueContext(['event' => 'mock'], [$user1, $user2]);

        $rule = new class($user1) implements EmailGuardRuleInterface {
            public function __construct(
                private readonly UserStub $matchUser,
            ) {}

            public function getName(): string
            {
                return 'test-gate';
            }

            public function getCost(): EmailGuardCost
            {
                return EmailGuardCost::Free;
            }

            public function evaluate(array $context): EmailGuardResult
            {
                return $context['user'] === $this->matchUser
                    ? EmailGuardResult::pass('test-gate')
                    : EmailGuardResult::skip('test-gate', 'not the chosen one');
            }
        };

        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);
        $email->expects($this->once())->method('send');
        $email->expects($this->once())->method('markContextSent')->with($dueContext);

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), new EmailGuardEvaluator());

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

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), new EmailGuardEvaluator());

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

        $service = new SendScheduledEmailsService(
            [$email1, $email2],
            $clock,
            new NullLogger(),
            new EmailGuardEvaluator(),
        );

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert: 1 from email1 + 2 from email2
        static::assertSame('3 emails queued', $result->message);
    }

    public function testGuardErrorIsLoggedAndLoopContinues(): void
    {
        // Arrange: user1 returns Error, user2 passes
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);
        $dueContext = new DueContext([], [$user1, $user2]);

        $rule = $this->errorRuleForUser($user1, 'missing-recipient', 'recipient missing');

        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);
        $email->expects($this->once())->method('send'); // only user2
        $email->expects($this->once())->method('markContextSent');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('guard rule returned Error - email skipped', $this->anything());

        $service = new SendScheduledEmailsService([$email], $clock, $logger, new EmailGuardEvaluator());

        // Act
        $result = $service->runCronTask(new BufferedOutput());

        // Assert
        static::assertSame('1 emails queued', $result->message);
    }

    public function testGuardErrorDedupedPerSweep(): void
    {
        // Arrange: 3 users, same rule fires Error every time - expect 1 log line only
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $users = [new UserStub()->setId(1), new UserStub()->setId(2), new UserStub()->setId(3)];
        $dueContext = new DueContext([], $users);

        $rule = $this->errorRuleAlways('missing-recipient', 'recipient missing');

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new SendScheduledEmailsService([$email], $clock, $logger, new EmailGuardEvaluator());

        // Act
        $service->runCronTask(new BufferedOutput());
    }

    public function testDistinctGuardErrorsAreAllLogged(): void
    {
        // Arrange: 2 users, 2 distinct error rule names - expect 2 log lines
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = new UserStub()->setId(1);
        $user2 = new UserStub()->setId(2);
        $dueContext = new DueContext([], [$user1, $user2]);

        $rule = new class($user1) implements EmailGuardRuleInterface {
            public function __construct(
                private readonly UserStub $user1,
            ) {}

            public function getName(): string
            {
                return 'dynamic';
            }

            public function getCost(): EmailGuardCost
            {
                return EmailGuardCost::Free;
            }

            public function evaluate(array $context): EmailGuardResult
            {
                $name = $context['user'] === $this->user1 ? 'missing-recipient' : 'missing-event';
                return EmailGuardResult::error($name, 'context error');
            }
        };

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $service = new SendScheduledEmailsService([$email], $clock, $logger, new EmailGuardEvaluator());

        // Act
        $service->runCronTask(new BufferedOutput());
    }

    private function errorRuleForUser(
        UserStub $matchUser,
        string $ruleName,
        string $explanation,
    ): EmailGuardRuleInterface {
        return new class($matchUser, $ruleName, $explanation) implements EmailGuardRuleInterface {
            public function __construct(
                private readonly UserStub $matchUser,
                private readonly string $ruleName,
                private readonly string $explanation,
            ) {}

            public function getName(): string
            {
                return $this->ruleName;
            }

            public function getCost(): EmailGuardCost
            {
                return EmailGuardCost::Free;
            }

            public function evaluate(array $context): EmailGuardResult
            {
                return $context['user'] === $this->matchUser
                    ? EmailGuardResult::error($this->ruleName, $this->explanation)
                    : EmailGuardResult::pass($this->ruleName);
            }
        };
    }

    private function errorRuleAlways(string $ruleName, string $explanation): EmailGuardRuleInterface
    {
        return new class($ruleName, $explanation) implements EmailGuardRuleInterface {
            public function __construct(
                private readonly string $ruleName,
                private readonly string $explanation,
            ) {}

            public function getName(): string
            {
                return $this->ruleName;
            }

            public function getCost(): EmailGuardCost
            {
                return EmailGuardCost::Free;
            }

            public function evaluate(array $context): EmailGuardResult
            {
                return EmailGuardResult::error($this->ruleName, $this->explanation);
            }
        };
    }
}
