<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit;

use DateTimeImmutable;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;
use Module\Email\Contract\GuardRuleProviderInterface;
use Module\Email\Internal\EmailQueueInterface;
use Module\Email\Internal\GuardEvaluator;
use Module\Email\Internal\Mailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

final class MailerTest extends TestCase
{
    public function testPassingChainQueuesEveryComposedMessage(): void
    {
        // Arrange
        $type = $this->type([$this->rule(GuardResult::pass('ok'))], ['a@example.com', 'b@example.com']);
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->exactly(2))->method('enqueue');

        // Act
        $outcome = $this->mailer($queue)->send($type, []);

        // Assert
        static::assertSame(GuardOutcome::Pass, $outcome->guard->outcome);
        static::assertSame(2, $outcome->queued);
    }

    public function testSkippingRuleComposesNothing(): void
    {
        // Arrange
        $type = $this->createMock(EmailInterface::class);
        $type->method('getGuardRules')->willReturn([$this->rule(GuardResult::skip('opted-out', 'toggle off'))]);
        $type->expects($this->never())->method('compose');
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        // Act
        $outcome = $this->mailer($queue)->send($type, []);

        // Assert
        static::assertSame('opted-out', $outcome->guard->ruleName);
        static::assertSame(0, $outcome->queued);
    }

    public function testErroringRuleIsReportedNotThrown(): void
    {
        // Arrange
        $type = $this->type([$this->rule(GuardResult::error('missing-user', 'no user'))], ['a@example.com']);
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->never())->method('enqueue');

        // Act
        $outcome = $this->mailer($queue)->send($type, []);

        // Assert
        static::assertSame(GuardOutcome::Error, $outcome->guard->outcome);
    }

    public function testBlocklistedRecipientIsDroppedAndTheOthersQueued(): void
    {
        // Arrange
        $type = $this->type([], ['blocked@example.com', 'fine@example.com']);
        $blocklist = $this->createStub(BlocklistInterface::class);
        $blocklist->method('isBlocked')->willReturnCallback(static fn(string $email): bool => $email === 'blocked@example.com');
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue
            ->expects($this->once())
            ->method('enqueue')
            ->with($type, static::callback(static fn(TemplatedEmail $email): bool => $email->getTo()[0]->getAddress() === 'fine@example.com'));

        // Act
        $outcome = $this->mailer($queue, $blocklist)->send($type, []);

        // Assert
        static::assertSame(1, $outcome->queued);
    }

    public function testProviderRulesJoinTheChain(): void
    {
        // Arrange
        $type = $this->createMock(EmailInterface::class);
        $type->method('getGuardRules')->willReturn([]);
        $type->expects($this->never())->method('compose');
        $provider = $this->createStub(GuardRuleProviderInterface::class);
        $provider->method('getRulesFor')->willReturn([$this->rule(GuardResult::skip('provided', 'provider said no'))]);
        $mailer = new Mailer(new GuardEvaluator([$provider]), $this->createStub(EmailQueueInterface::class), $this->createStub(BlocklistInterface::class));

        // Act
        $outcome = $mailer->send($type, []);

        // Assert
        static::assertSame('provided', $outcome->guard->ruleName);
    }

    public function testFlushAndPushPreferencePassThroughToTheQueue(): void
    {
        // Arrange
        $type = $this->type([], ['a@example.com'], pushOnEnqueue: false);
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')->with($type, static::anything(), ['key' => 'value'], false, null, false);

        // Act
        $this->mailer($queue)->send($type, ['key' => 'value'], flush: false);
    }

    public function testDispatchPushIsHandedToTheQueue(): void
    {
        // Arrange
        $deadline = new DateTimeImmutable('2031-01-01 12:00');
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('dispatchPush')->with('message', 'a@example.com', $deadline);
        $queue->expects($this->never())->method('enqueue');

        // Act
        $this->mailer($queue)->dispatchPush('message', 'a@example.com', $deadline);
    }

    private function mailer(EmailQueueInterface $queue, ?BlocklistInterface $blocklist = null): Mailer
    {
        return new Mailer(new GuardEvaluator(), $queue, $blocklist ?? $this->createStub(BlocklistInterface::class));
    }

    /**
     * @param list<GuardRuleInterface> $rules
     * @param list<string> $recipients
     */
    private function type(array $rules, array $recipients, bool $pushOnEnqueue = true): EmailInterface
    {
        $type = $this->createStub(EmailInterface::class);
        $type->method('getGuardRules')->willReturn($rules);
        $type->method('pushOnEnqueue')->willReturn($pushOnEnqueue);
        $type->method('compose')->willReturn(array_map(static fn(string $to): TemplatedEmail => new TemplatedEmail()->to($to), $recipients));

        return $type;
    }

    private function rule(GuardResult $result): GuardRuleInterface
    {
        return new readonly class($result) implements GuardRuleInterface {
            public function __construct(
                private GuardResult $result,
            ) {}

            public function getName(): string
            {
                return $this->result->ruleName;
            }

            public function getCost(): GuardCost
            {
                return GuardCost::Free;
            }

            public function evaluate(array $context): GuardResult
            {
                return $this->result;
            }
        };
    }
}
