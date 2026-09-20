<?php declare(strict_types=1);

namespace Tests\Unit\Service\Member;

use App\Activity\ActivityService;
use App\Activity\Messages\SendMessage;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\Member\BlockingService;
use App\Service\Member\MessageService;
use App\Service\Security\ContentSanitizer;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class MessageServiceTest extends TestCase
{
    public function testSendPersistsSanitizedContentAndLogsTheActivityOnce(): void
    {
        // Arrange
        $sender = $this->user(1);
        $receiver = $this->user(2);

        $blockingService = $this->createStub(BlockingService::class);
        $blockingService->method('isBlocked')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $activityService = $this->createMock(ActivityService::class);
        $activityService->expects($this->once())->method('log')->with(SendMessage::TYPE, $sender, ['user_id' => 2]);

        $subject = new MessageService($em, $this->createStub(MessageRepository::class), $blockingService, $this->sanitizer(), $activityService);

        // Act
        $message = $subject->send($sender, $receiver, '  <script>alert(1)</script>hello  ');

        // Assert
        self::assertSame('hello', $message->getContent());
        self::assertSame($sender, $message->getSender());
        self::assertSame($receiver, $message->getReceiver());
        self::assertFalse($message->isWasRead());
        self::assertFalse($message->isDeleted());
    }

    public function testSendAcrossABlockIsRefusedAndNothingIsWritten(): void
    {
        // Arrange
        $blockingService = $this->createStub(BlockingService::class);
        $blockingService->method('isBlocked')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $activityService = $this->createMock(ActivityService::class);
        $activityService->expects($this->never())->method('log');

        $subject = new MessageService($em, $this->createStub(MessageRepository::class), $blockingService, $this->sanitizer(), $activityService);

        // Act & Assert
        $this->expectException(DomainException::class);
        $subject->send($this->user(1), $this->user(2), 'hello');
    }

    public function testSendingToYourselfIsRefused(): void
    {
        // Arrange
        $subject = new MessageService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(MessageRepository::class),
            $this->createStub(BlockingService::class),
            $this->sanitizer(),
            $this->createStub(ActivityService::class),
        );

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $subject->send($this->user(7), $this->user(7), 'hello');
    }

    public function testEditStoresSanitizedContentAndStampsTheEditTime(): void
    {
        // Arrange
        $message = new Message();
        $message->setContent('before');
        $editedAt = new DateTimeImmutable('2026-09-20 12:00:00');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $subject = new MessageService(
            $em,
            $this->createStub(MessageRepository::class),
            $this->createStub(BlockingService::class),
            $this->sanitizer(),
            $this->createStub(ActivityService::class),
        );

        // Act
        $subject->edit($message, '  <b>after</b>  ', $editedAt);

        // Assert
        self::assertSame('after', $message->getContent());
        self::assertSame($editedAt, $message->getEditedAt());
    }

    public function testFindEditableDelegatesToTheRepositoryWindowQuery(): void
    {
        // Arrange
        $sender = $this->user(1);
        $now = new DateTimeImmutable('2026-09-20 12:00:00');
        $expired = null;

        $repo = $this->createMock(MessageRepository::class);
        $repo->expects($this->once())->method('findEditableForSender')->with(42, $sender, $now)->willReturn($expired);

        $subject = new MessageService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(BlockingService::class),
            $this->sanitizer(),
            $this->createStub(ActivityService::class),
        );

        // Act
        $result = $subject->findEditable(42, $sender, $now);

        // Assert
        self::assertNull($result);
    }

    public function testMarkReadDelegatesToTheRepository(): void
    {
        // Arrange
        $user = $this->user(1);
        $partner = $this->user(2);

        $repo = $this->createMock(MessageRepository::class);
        $repo->expects($this->once())->method('markConversationRead')->with($user, $partner);

        $subject = new MessageService(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            $this->createStub(BlockingService::class),
            $this->sanitizer(),
            $this->createStub(ActivityService::class),
        );

        // Act & Assert
        $subject->markRead($user, $partner);
    }

    private function sanitizer(): ContentSanitizer
    {
        $config = new HtmlSanitizerConfig()->allowSafeElements();

        return new ContentSanitizer(new HtmlSanitizer($config), new HtmlSanitizer($config));
    }

    private function user(int $id): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }
}
