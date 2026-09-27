<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\QueueStatus;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\SentEmail;
use Module\Email\Internal\EmailService;
use Module\Email\Tests\Stub\TriggeredEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SendlogTest extends KernelTestCase
{
    use SeedsTemplates;

    private const string ANN = 'ann@module-test.example';
    private const string BOB = 'bob@module-test.example';

    private SendlogInterface $sendlog;

    protected function setUp(): void
    {
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $this->sendlog = self::getContainer()->get(SendlogInterface::class);
        self::getContainer()
            ->get(MailerInterface::class)
            ->send(self::getContainer()->get(TriggeredEmail::class), ['recipients' => ['Ann Example <' . self::ANN . '>', self::BOB]]);
    }

    public function testTheRecipientFilterMatchesAnAddressStoredWithADisplayName(): void
    {
        // Act
        $rows = $this->sendlog->list(recipient: self::ANN);

        // Assert
        self::assertSame(['"Ann Example" <' . self::ANN . '>'], $this->recipients($rows));
    }

    public function testTheTemplateFilterKeepsOnlyThatTemplate(): void
    {
        // Act
        $rows = $this->sendlog->list(template: 'another_template');

        // Assert
        self::assertSame([], $rows);
    }

    public function testTheStatusFilterFollowsDispatch(): void
    {
        // Arrange
        self::getContainer()->get(EmailService::class)->sendQueue();

        // Act
        $sent = $this->sendlog->list(template: TriggeredEmail::IDENTIFIER, status: QueueStatus::Sent);
        $pending = $this->sendlog->list(template: TriggeredEmail::IDENTIFIER, status: QueueStatus::Pending);

        // Assert
        self::assertCount(2, $sent);
        self::assertSame([], $pending);
    }

    public function testStatsCountPendingRowsAndThoseWaitingOverAnHour(): void
    {
        // Arrange
        $bob = $this->sendlog->list(recipient: self::BOB)[0];
        self::getContainer()
            ->get(Connection::class)
            ->update('mod_email_queue', ['created_at' => new DateTimeImmutable('-2 hours')], ['id' => $bob->id], ['created_at' => Types::DATETIME_IMMUTABLE]);

        // Act
        $stats = $this->sendlog->stats();

        // Assert
        self::assertSame([2, 1], [$stats->pending, $stats->stale]);
    }

    public function testFindReturnsARowByItsIdAndNothingForAnUnknownOne(): void
    {
        // Arrange
        $bob = $this->sendlog->list(recipient: self::BOB)[0];

        // Act
        $found = [$this->sendlog->find($bob->id)?->recipient, $this->sendlog->find($bob->id + 1000)];

        // Assert
        self::assertSame([self::BOB, null], $found);
    }

    /**
     * @param list<SentEmail> $rows
     * @return list<string>
     */
    private function recipients(array $rows): array
    {
        return array_map(static fn(SentEmail $row): string => $row->recipient, $rows);
    }
}
