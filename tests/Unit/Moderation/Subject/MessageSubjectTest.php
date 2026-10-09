<?php declare(strict_types=1);

namespace Tests\Unit\Moderation\Subject;

use App\Entity\Message;
use App\Moderation\Subject\MessageSubject;
use App\Service\Member\MessageService;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\UserStub;

class MessageSubjectTest extends TestCase
{
    public function testReceiverMayReportAndTheMarkerIsStripped(): void
    {
        // Arrange
        $sender = new UserStub()
            ->setId(2)
            ->setName('Orlando');
        $content = Message::SUPPORT_QUESTION_MARKER . 'Cheap watches';
        $subject = $this->makeSubject($this->makeMessage($sender, new UserStub()->setId(1), $content));

        // Act
        $snapshot = $subject->describe(5, new UserStub()->setId(1));

        // Assert
        static::assertNotNull($snapshot);
        static::assertSame('Orlando', $snapshot->label);
        static::assertSame('Cheap watches', $snapshot->excerpt);
        static::assertSame($sender, $snapshot->author);
    }

    public function testOnlyTheReceiverMayReport(): void
    {
        // Arrange
        $message = $this->makeMessage(new UserStub()->setId(2), new UserStub()->setId(3), 'Hello');
        $subject = $this->makeSubject($message);

        // Act
        $snapshot = $subject->describe(5, new UserStub()->setId(1));

        // Assert
        static::assertNull($snapshot);
    }

    public function testMissingMessageIsRefused(): void
    {
        // Arrange
        $subject = $this->makeSubject(null);

        // Act
        $snapshot = $subject->describe(5, new UserStub()->setId(1));

        // Assert
        static::assertNull($snapshot);
    }

    private function makeMessage(UserStub $sender, UserStub $receiver, string $content): Message
    {
        return new Message()
            ->setSender($sender)
            ->setReceiver($receiver)
            ->setContent($content);
    }

    private function makeSubject(?Message $message): MessageSubject
    {
        $service = $this->createStub(MessageService::class);
        $service->method('findMessage')->willReturn($message);

        return new MessageSubject($service);
    }
}
