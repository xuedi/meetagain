<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Internal;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\DueContext;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\ScheduledEmailInterface;
use Module\Email\Internal\EmailQueueInterface;
use Module\Email\Internal\GuardEvaluator;
use Module\Email\Internal\Mailer;
use Module\Email\Internal\SendScheduledEmailsService;
use Module\Email\Tests\Stub\GuardRule;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Output\BufferedOutput;

final class SendScheduledEmailsServiceTest extends TestCase
{
    public function testSkipsAtOrAfter22(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 22:00:00'));
        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->expects($this->never())->method('getDueContexts');

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $this->mailer(), $this->createStub(EntityManagerInterface::class));

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

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $this->mailer(), $this->createStub(EntityManagerInterface::class));

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

        $rule = GuardRule::deciding('test-gate', static fn(array $context): GuardResult => $context['user'] === $user1
            ? GuardResult::pass('test-gate')
            : GuardResult::skip('test-gate', 'not the chosen one'));

        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);
        $email->expects($this->once())->method('compose')->willReturn([$this->message()]);
        $email->expects($this->once())->method('markContextSent')->with($dueContext);

        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $this->mailer(), $this->createStub(EntityManagerInterface::class));

        // Act
        $output = new BufferedOutput();
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame('1 emails queued', $result->message);
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

        $service = new SendScheduledEmailsService(
            [$email1, $email2],
            $clock,
            new NullLogger(),
            $this->mailer(),
            $this->createStub(EntityManagerInterface::class),
        );

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

        $rule = GuardRule::deciding('missing-recipient', static fn(array $context): GuardResult => $context['user'] === $user1
            ? GuardResult::error('missing-recipient', 'recipient missing')
            : GuardResult::pass('missing-recipient'));

        $email = $this->createMock(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);
        $email->expects($this->once())->method('compose')->willReturn([$this->message()]); // only user2
        $email->expects($this->once())->method('markContextSent');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('guard rule returned Error - email skipped', $this->anything());

        $service = new SendScheduledEmailsService([$email], $clock, $logger, $this->mailer(), $this->createStub(EntityManagerInterface::class));

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

        $rule = GuardRule::returning(GuardResult::error('missing-recipient', 'recipient missing'));

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new SendScheduledEmailsService([$email], $clock, $logger, $this->mailer(), $this->createStub(EntityManagerInterface::class));

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

        $rule = GuardRule::deciding('dynamic', static fn(array $context): GuardResult => GuardResult::error(
            $context['user'] === $user1 ? 'missing-recipient' : 'missing-event',
            'context error',
        ));

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getIdentifier')->willReturn('test.email');
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([$rule]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $service = new SendScheduledEmailsService([$email], $clock, $logger, $this->mailer(), $this->createStub(EntityManagerInterface::class));

        // Act
        $service->runCronTask(new BufferedOutput());
    }

    private function message(): TemplatedEmail
    {
        return new TemplatedEmail()->to('member@example.com');
    }

    public function testTheSweepFlushesEveryChunkAndBeforeTheContextIsMarkedSent(): void
    {
        // Arrange
        $clock = new MockClock(new DateTimeImmutable('2026-04-12 10:00:00'));
        $users = array_map($this->user(...), range(1, 450));
        $dueContext = new DueContext(['event' => 'mock'], $users);
        $log = [];

        $email = $this->createStub(ScheduledEmailInterface::class);
        $email->method('getDueContexts')->willReturn([$dueContext]);
        $email->method('getGuardRules')->willReturn([]);
        $email->method('compose')->willReturnCallback(fn(): array => [$this->message()]);
        $email
            ->method('markContextSent')
            ->willReturnCallback(static function () use (&$log): void {
                $log[] = 'marked';
            });

        $queue = $this->createStub(EmailQueueInterface::class);
        $queue
            ->method('enqueue')
            ->willReturnCallback(static function (mixed $source, mixed $message, array $context, bool $flush) use (&$log): bool {
                $log[] = $flush ? 'enqueue+flush' : 'enqueue';

                return true;
            });
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willReturnCallback(static function () use (&$log): void {
            $log[] = 'flush';
        });

        $mailer = new Mailer(new GuardEvaluator(), $queue, $this->createStub(BlocklistInterface::class));
        $service = new SendScheduledEmailsService([$email], $clock, new NullLogger(), $mailer, $em);

        // Act
        $result = $service->runCronTask(new BufferedOutput());

        // Assert
        static::assertSame('450 emails queued', $result->message);
        static::assertNotContains('enqueue+flush', $log);
        $flushes = array_keys($log, 'flush', true);
        static::assertCount(3, $flushes);
        static::assertSame(200, $flushes[0]);
        static::assertSame(['flush', 'marked'], array_slice($log, -2));
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
