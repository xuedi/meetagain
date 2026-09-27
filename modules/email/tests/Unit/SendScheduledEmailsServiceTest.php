<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit;

use App\Entity\User;
use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\DueContext;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;
use Module\Email\Contract\ScheduledEmailInterface;
use Module\Email\Internal\EmailQueueInterface;
use Module\Email\Internal\GuardEvaluator;
use Module\Email\Internal\Mailer;
use Module\Email\Internal\SendScheduledEmailsService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Output\BufferedOutput;

final class SendScheduledEmailsServiceTest extends TestCase
{
    public function testSkipsOutsideAllowedHours(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 06:00:00'));
        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->expects($this->never())->method('getDueContexts');

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $this->mailer());

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

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $this->mailer());

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

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $this->mailer());

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

        $user1 = $this->user(1);
        $user2 = $this->user(2);

        $dueContext = new DueContext(['event' => 'mock'], [$user1, $user2]);

        $rule = new class($user1) implements GuardRuleInterface {
            public function __construct(
                private readonly User $matchUser,
            ) {}

            public function getName(): string
            {
                return 'test-gate';
            }

            public function getCost(): GuardCost
            {
                return GuardCost::Free;
            }

            public function evaluate(array $context): GuardResult
            {
                return $context['user'] === $this->matchUser ? GuardResult::pass('test-gate') : GuardResult::skip('test-gate', 'not the chosen one');
            }
        };

        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);
        $email->expects($this->once())->method('compose')->willReturn([$this->message()]);
        $email->expects($this->once())->method('markContextSent')->with($dueContext);

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $this->mailer());

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

        $user1 = $this->user(1);
        $user2 = $this->user(2);

        $dueContext = new DueContext([], [$user1, $user2]);

        $callOrder = [];

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('compose')->willReturn([$this->message()]);
        $message = $this->message();
        $email
            ->method('compose')
            ->willReturnCallback(static function () use (&$callOrder, $message) {
                $callOrder[] = 'compose';

                return [$message];
            });
        $email
            ->method('markContextSent')
            ->willReturnCallback(static function () use (&$callOrder) {
                $callOrder[] = 'markContextSent';
            });

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $this->mailer());

        // Act
        $output = new BufferedOutput();
        $service->runCronTask($output);

        // Assert
        static::assertSame(['compose', 'compose', 'markContextSent'], $callOrder);
    }

    public function testTotalSentCountAcrossMultipleEmailTypes(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user = $this->user(1);
        $ctx1 = new DueContext([], [$user]);
        $ctx2 = new DueContext([], [$user, $this->user(2)]);

        $email1 = $this->createStub(ScheduledEmailInterface::class);
        $email1->method('getDueContexts')->willReturn([$ctx1]);
        $email1->method('compose')->willReturn([$this->message()]);

        $email2 = $this->createStub(ScheduledEmailInterface::class);
        $email2->method('getDueContexts')->willReturn([$ctx2]);
        $email2->method('compose')->willReturn([$this->message()]);

        $service = new SendScheduledEmailsService([$email1, $email2], $clock, new NullLogger(), $this->mailer());

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame('3 emails queued', $result->message);
    }

    public function testGuardErrorIsLoggedAndLoopContinues(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = $this->user(1);
        $user2 = $this->user(2);
        $dueContext = new DueContext([], [$user1, $user2]);

        $rule = $this->errorRuleForUser($user1, 'missing-recipient', 'recipient missing');

        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);
        $email->expects($this->once())->method('compose')->willReturn([$this->message()]); // only user2
        $email->expects($this->once())->method('markContextSent');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('guard rule returned Error - email skipped', $this->anything());

        $service = new SendScheduledEmailsService([$email], $clock, $logger, $this->mailer());

        // Act
        $result = $service->runCronTask(new BufferedOutput());

        // Assert
        static::assertSame('1 emails queued', $result->message);
    }

    public function testGuardErrorDedupedPerSweep(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $users = [$this->user(1), $this->user(2), $this->user(3)];
        $dueContext = new DueContext([], $users);

        $rule = $this->errorRuleAlways('missing-recipient', 'recipient missing');

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new SendScheduledEmailsService([$email], $clock, $logger, $this->mailer());

        // Act
        $service->runCronTask(new BufferedOutput());
    }

    public function testDistinctGuardErrorsAreAllLogged(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));

        $user1 = $this->user(1);
        $user2 = $this->user(2);
        $dueContext = new DueContext([], [$user1, $user2]);

        $rule = new class($user1) implements GuardRuleInterface {
            public function __construct(
                private readonly User $user1,
            ) {}

            public function getName(): string
            {
                return 'dynamic';
            }

            public function getCost(): GuardCost
            {
                return GuardCost::Free;
            }

            public function evaluate(array $context): GuardResult
            {
                $name = $context['user'] === $this->user1 ? 'missing-recipient' : 'missing-event';
                return GuardResult::error($name, 'context error');
            }
        };

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $service = new SendScheduledEmailsService([$email], $clock, $logger, $this->mailer());

        // Act
        $service->runCronTask(new BufferedOutput());
    }

    private function errorRuleForUser(User $matchUser, string $ruleName, string $explanation): GuardRuleInterface
    {
        return new class($matchUser, $ruleName, $explanation) implements GuardRuleInterface {
            public function __construct(
                private readonly User $matchUser,
                private readonly string $ruleName,
                private readonly string $explanation,
            ) {}

            public function getName(): string
            {
                return $this->ruleName;
            }

            public function getCost(): GuardCost
            {
                return GuardCost::Free;
            }

            public function evaluate(array $context): GuardResult
            {
                return $context['user'] === $this->matchUser ? GuardResult::error($this->ruleName, $this->explanation) : GuardResult::pass($this->ruleName);
            }
        };
    }

    private function errorRuleAlways(string $ruleName, string $explanation): GuardRuleInterface
    {
        return new class($ruleName, $explanation) implements GuardRuleInterface {
            public function __construct(
                private readonly string $ruleName,
                private readonly string $explanation,
            ) {}

            public function getName(): string
            {
                return $this->ruleName;
            }

            public function getCost(): GuardCost
            {
                return GuardCost::Free;
            }

            public function evaluate(array $context): GuardResult
            {
                return GuardResult::error($this->ruleName, $this->explanation);
            }
        };
    }

    private function message(): TemplatedEmail
    {
        return new TemplatedEmail()->to('member@example.com');
    }

    private function mailer(): Mailer
    {
        return new Mailer(new GuardEvaluator(), $this->createStub(EmailQueueInterface::class), $this->createStub(BlocklistInterface::class));
    }

    private function user(int $id): User
    {
        $user = new User();
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
