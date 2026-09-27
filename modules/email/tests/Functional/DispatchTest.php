<?php declare(strict_types=1);

namespace Module\Email\Tests\Functional;

use DateTimeImmutable;
use Module\Email\Contract\MailerInterface;
use Module\Email\Contract\QueueStatus;
use Module\Email\Contract\SendlogInterface;
use Module\Email\Contract\SentEmail;
use Module\Email\Internal\EmailService;
use Module\Email\Tests\Stub\IdentityProvider;
use Module\Email\Tests\Stub\Origin;
use Module\Email\Tests\Stub\TriggeredEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Mime\Email;

final class DispatchTest extends KernelTestCase
{
    use SeedsTemplates;

    private const string RECIPIENT = 'member@module-test.example';

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

    public function testASentRowIsNotSentAgainOnTheNextTick(): void
    {
        // Arrange
        $this->queue();
        $this->runQueueTask();

        // Act
        $second = $this->runQueueTask();

        // Assert
        self::assertSame('EmailService: 0', trim($second));
    }

    private function queue(): void
    {
        self::getContainer()
            ->get(MailerInterface::class)
            ->send($this->type, ['recipients' => [self::RECIPIENT], 'origin' => new Origin()]);
    }

    private function runQueueTask(): string
    {
        $output = new BufferedOutput();
        self::getContainer()->get(EmailService::class)->runCronTask($output);

        return $output->fetch();
    }

    private function row(): SentEmail
    {
        $rows = self::getContainer()->get(SendlogInterface::class)->list(template: TriggeredEmail::IDENTIFIER);
        self::assertCount(1, $rows);

        return $rows[0];
    }
}
