<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\QueueStatus;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\SentEmail;
use Module\Email\Internal\EmailService;
use Module\Email\Internal\Message\Drain;
use Module\Email\Tests\Stub\IdentityProvider;
use Module\Email\Tests\Stub\Origin;
use Module\Email\Tests\Stub\TriggeredEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Email;

final class DispatchTest extends KernelTestCase
{
    use SeedsTemplates;

    private const string RECIPIENT = 'member@module-test.example';
    private const string SECOND_RECIPIENT = 'second@module-test.example';

    private TriggeredEmail $type;

    protected function setUp(): void
    {
        self::bootKernel();
        self::seedTemplates(self::$kernel);
        $this->type = self::getContainer()->get(TriggeredEmail::class);
    }

    public function testTheQueueTaskSendsAPendingRowInsideItsFrozenLayout(): void
    {
        // Arrange
        $this->queue();

        // Act
        $this->runQueueTask();

        // Assert
        $row = $this->row();
        self::assertSame(QueueStatus::Sent, $row->status);
        self::assertNotNull($row->dispatchedAt);
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertStringContainsString('<!DOCTYPE html>', (string) $message->getHtmlBody());
        self::assertStringContainsString(IdentityProvider::SITE_NAME, (string) $message->getHtmlBody());
    }

    public function testARowPastItsCutoffIsMarkedLateAndNotSent(): void
    {
        // Arrange
        $this->type->maxSendBy = new DateTimeImmutable('-1 minute');
        $this->queue();

        // Act
        $this->runQueueTask();

        // Assert
        self::assertSame(QueueStatus::Late, $this->row()->status);
        self::assertEmailCount(0);
    }

    public function testAMailThatCannotBeBuiltFailsAloneAndTheRestAreStillSent(): void
    {
        // Arrange
        self::getContainer()
            ->get(MailerInterface::class)
            ->send($this->type, ['recipients' => [self::RECIPIENT, self::SECOND_RECIPIENT], 'origin' => new Origin()]);
        self::getContainer()->get(Connection::class)->update('mod_email_queue', ['sender' => 'not an address'], ['recipient' => self::RECIPIENT]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        // Act
        $this->runQueueTask();

        // Assert
        $rows = self::getContainer()->get(SendlogInterface::class)->list(template: TriggeredEmail::IDENTIFIER);
        $statuses = array_combine(
            array_map(static fn(SentEmail $row): string => $row->recipient, $rows),
            array_map(static fn(SentEmail $row): QueueStatus => $row->status, $rows),
        );
        self::assertSame(QueueStatus::Failed, $statuses[self::RECIPIENT]);
        self::assertSame(QueueStatus::Sent, $statuses[self::SECOND_RECIPIENT]);
        self::assertEmailCount(1);
    }

    public function testASentRowIsNotSentAgainOnTheNextTick(): void
    {
        // Arrange
        $this->queue();
        $this->runQueueTask();

        // Act
        $second = $this->runQueueTask();

        // Assert
        self::assertSame('0', $second);
    }

    public function testQueueingAMailWakesAWorker(): void
    {
        // Arrange
        $transport = self::getContainer()->get('messenger.transport.async');

        // Act
        $this->queue();

        // Assert
        $sent = array_map(static fn($envelope): object => $envelope->getMessage(), $transport->getSent());
        self::assertCount(1, array_filter($sent, static fn(object $message): bool => $message instanceof Drain));
    }

    public function testTheWokenWorkerSendsTheRow(): void
    {
        // Arrange
        $this->queue();

        // Act
        self::getContainer()->get(EmailService::class)->drain(new Drain());

        // Assert
        self::assertSame(QueueStatus::Sent, $this->row()->status);
        self::assertEmailCount(1);
    }

    private function queue(): void
    {
        self::getContainer()
            ->get(MailerInterface::class)
            ->send($this->type, ['recipients' => [self::RECIPIENT], 'origin' => new Origin()]);
    }

    private function runQueueTask(): string
    {
        return self::getContainer()->get(EmailService::class)->run()->message;
    }

    private function row(): SentEmail
    {
        $rows = self::getContainer()->get(SendlogInterface::class)->list(template: TriggeredEmail::IDENTIFIER);
        self::assertCount(1, $rows);

        return $rows[0];
    }
}
