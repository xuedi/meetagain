<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\QueueStatus;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\SentEmail;
use Module\Email\Tests\Stub\GuardRule;
use Module\Email\Tests\Stub\GuardRuleProvider;
use Module\Email\Tests\Stub\TriggeredEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SendTest extends KernelTestCase
{
    use SeedsTemplates;

    private const string FIRST = 'first@module-test.example';
    private const string SECOND = 'second@module-test.example';

    private TriggeredEmail $type;

    private MailerInterface $mailer;

    protected function setUp(): void
    {
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $this->type = self::getContainer()->get(TriggeredEmail::class);
        $this->mailer = self::getContainer()->get(MailerInterface::class);
    }

    public function testAPassingChainQueuesOnePendingRowPerComposedMessage(): void
    {
        // Act
        $outcome = $this->mailer->send($this->type, ['recipients' => [self::FIRST, self::SECOND], 'name' => 'Ann']);

        // Assert
        self::assertSame(GuardOutcome::Pass, $outcome->guard->outcome);
        self::assertSame(2, $outcome->queued);
        self::assertSame([self::FIRST, self::SECOND], array_map(static fn(SentEmail $row): string => $row->recipient, $this->queued()));
        self::assertSame([QueueStatus::Pending, QueueStatus::Pending], array_map(static fn(SentEmail $row): QueueStatus => $row->status, $this->queued()));
    }

    public function testASkippingRuleQueuesNothing(): void
    {
        // Arrange
        $this->type->rules = [GuardRule::returning(GuardResult::skip('opted-out', 'the member opted out'))];

        // Act
        $outcome = $this->mailer->send($this->type, ['recipients' => [self::FIRST]]);

        // Assert
        self::assertSame('opted-out', $outcome->guard->ruleName);
        self::assertSame([], $this->queued());
    }

    public function testAnErroringRuleIsReportedAndQueuesNothing(): void
    {
        // Arrange
        $this->type->rules = [GuardRule::returning(GuardResult::error('missing-user', 'no user in the context'))];

        // Act
        $outcome = $this->mailer->send($this->type, ['recipients' => [self::FIRST]]);

        // Assert
        self::assertSame(GuardOutcome::Error, $outcome->guard->outcome);
        self::assertSame([], $this->queued());
    }

    public function testARuleFromAProviderJoinsTheTypesOwnChain(): void
    {
        // Arrange
        $this->type->rules = [GuardRule::returning(GuardResult::pass('own'))];
        self::getContainer()->get(GuardRuleProvider::class)->rules[TriggeredEmail::IDENTIFIER] = [GuardRule::returning(GuardResult::skip(
            'provided',
            'a provider said no',
        ))];

        // Act
        $outcome = $this->mailer->send($this->type, ['recipients' => [self::FIRST]]);

        // Assert
        self::assertSame('provided', $outcome->guard->ruleName);
        self::assertSame([], $this->queued());
    }

    public function testABlocklistedRecipientIsDroppedAndTheOthersQueued(): void
    {
        // Arrange
        self::getContainer()->get(BlocklistInterface::class)->add(strtoupper(self::FIRST), 'bounced');

        // Act
        $outcome = $this->mailer->send($this->type, ['recipients' => [self::FIRST, self::SECOND]]);

        // Assert
        self::assertSame(1, $outcome->queued);
        self::assertSame([self::SECOND], array_map(static fn(SentEmail $row): string => $row->recipient, $this->queued()));
    }

    /**
     * @return list<SentEmail>
     */
    private function queued(): array
    {
        return array_reverse(self::getContainer()->get(SendlogInterface::class)->list(template: TriggeredEmail::IDENTIFIER));
    }
}
