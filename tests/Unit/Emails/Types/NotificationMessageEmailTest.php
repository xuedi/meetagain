<?php declare(strict_types=1);

namespace Tests\Unit\Emails\Types;

use App\Emails\DueContext;
use App\Emails\EmailQueueInterface;
use App\Emails\Types\NotificationMessageEmail;
use App\Entity\NotificationSettings;
use App\Repository\MessageRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Tests\Unit\Emails\SampleFactoryTrait;
use Tests\Unit\Stubs\UserStub;

final class NotificationMessageEmailTest extends TestCase
{
    use SampleFactoryTrait;

    public function testDueContextsAskForPairsWhoseFirstUnreadMessageIsThreeHoursOld(): void
    {
        // Arrange
        $repo = $this->createMock(MessageRepository::class);
        $repo->expects($this->once())->method('findUnremindedPairs')->with(null, new DateTimeImmutable('2026-09-21 09:00:00'))->willReturn([]);

        // Act
        $contexts = $this->email($repo)->getDueContexts(new DateTimeImmutable('2026-09-21 12:00:00'));

        // Assert
        static::assertSame([], $contexts);
    }

    public function testEachPairBecomesOneContextAddressedToItsReceiver(): void
    {
        // Arrange
        $alice = new UserStub()->setId(1);
        $bob = new UserStub()->setId(2);
        $carol = new UserStub()->setId(3);
        $repo = $this->createStub(MessageRepository::class);
        $repo->method('findUnremindedPairs')->willReturn([
            ['sender' => $alice, 'receiver' => $bob, 'firstUnread' => new DateTimeImmutable('2026-09-21 08:00:00')],
            ['sender' => $carol, 'receiver' => $bob, 'firstUnread' => new DateTimeImmutable('2026-09-21 08:30:00')],
        ]);

        // Act
        $contexts = $this->email($repo)->getDueContexts(new DateTimeImmutable('2026-09-21 12:00:00'));

        // Assert
        static::assertCount(2, $contexts);
        static::assertSame(['sender' => $alice, 'recipient' => $bob], $contexts[0]->data);
        static::assertSame([$bob], $contexts[0]->potentialRecipients);
        static::assertSame($carol, $contexts[1]->data['sender']);
    }

    public function testMarkingAContextSentStampsThatPair(): void
    {
        // Arrange
        $alice = new UserStub()->setId(1);
        $bob = new UserStub()->setId(2);
        $repo = $this->createMock(MessageRepository::class);
        $repo->expects($this->once())->method('markReminderSent')->with($alice, $bob, $this->isInstanceOf(DateTimeImmutable::class));

        // Act
        $this->email($repo)->markContextSent(new DueContext(['sender' => $alice, 'recipient' => $bob], [$bob]));

        // Assert
    }

    public function testTheEmailIsQueuedWithoutAPing(): void
    {
        // Arrange
        $queue = $this->createMock(EmailQueueInterface::class);
        $queue->expects($this->once())->method('enqueue')->with($this->anything(), $this->anything(), $this->anything(), true, null, false);
        $config = $this->createStub(ConfigService::class);
        $config->method('getMailerAddress')->willReturn(new Address('noreply@example.com'));
        $email = new NotificationMessageEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->mockSampleFactory(),
            $queue,
            $config,
            $this->createStub(MessageRepository::class),
        );
        $sender = new UserStub()
            ->setId(1)
            ->setName('Alice');
        $recipient = new UserStub()
            ->setId(2)
            ->setName('Bob')
            ->setEmail('bob@example.com');

        // Act
        $email->send(['sender' => $sender, 'recipient' => $recipient]);

        // Assert
    }

    public function testAPlannedItemIsDueThreeHoursAfterTheFirstUnreadMessage(): void
    {
        // Arrange
        $sender = new UserStub()
            ->setId(1)
            ->setEmail('alice@example.com');
        $receiver = new UserStub()
            ->setId(2)
            ->setName('Bob')
            ->setEmail('bob@example.com');
        $receiver->setNotification(true);
        $receiver->setNotificationSettings(new NotificationSettings(['receivedMessage' => true]));
        $repo = $this->createMock(MessageRepository::class);
        $repo
            ->expects($this->once())
            ->method('findUnremindedPairs')
            ->with(new DateTimeImmutable('2026-09-21 07:00:00'), new DateTimeImmutable('2026-09-21 19:00:00'))
            ->willReturn([['sender' => $sender, 'receiver' => $receiver, 'firstUnread' => new DateTimeImmutable('2026-09-21 08:15:00')]]);

        // Act
        $items = $this->email($repo)->getPlannedItems(new DateTimeImmutable('2026-09-21 10:00:00'), new DateTimeImmutable('2026-09-21 22:00:00'));

        // Assert
        static::assertCount(1, $items);
        static::assertSame('2026-09-21 11:15:00', $items[0]->expectedTime->format('Y-m-d H:i:s'));
        static::assertSame(1, $items[0]->expectedRecipients);
    }

    private function email(MessageRepository $repo): NotificationMessageEmail
    {
        return new NotificationMessageEmail(
            $this->createStub(BlocklistCheckerInterface::class),
            $this->mockSampleFactory(),
            $this->createStub(EmailQueueInterface::class),
            $this->createStub(ConfigService::class),
            $repo,
        );
    }
}
