<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Activity\ActivityService;
use App\Activity\MessageFactory;
use App\Activity\MessageInterface;
use App\Activity\Messages\Login;
use App\Activity\NotificationService as ActivityNotificationService;
use App\Entity\Activity;
use App\Entity\User;
use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ActivityServiceTest extends TestCase
{
    public function testLog(): void
    {
        // Arrange
        $type = Login::TYPE;
        $user = $this->createStub(User::class);
        $meta = ['key' => 'value'];

        // Arrange
        $messageMock = $this->createMock(MessageInterface::class);
        $messageMock->expects($this->once())->method('validate');

        // Arrange
        $messageFactoryMock = $this->createMock(MessageFactory::class);
        $messageFactoryMock->expects($this->once())->method('build')->willReturn($messageMock);

        // Arrange
        $notificationServiceMock = $this->createMock(ActivityNotificationService::class);
        $notificationServiceMock->expects($this->once())->method('notify');

        // Arrange
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(
                static fn(Activity $activity) => (
                    $activity->getType() === $type
                    && $activity->getUser() === $user
                    && $activity->getMeta() === $meta
                    && $activity->getCreatedAt() instanceof DateTimeImmutable
                ),
            ));
        $entityManagerMock->expects($this->once())->method('flush');

        // Arrange
        $subject = new ActivityService(
            em: $entityManagerMock,
            repo: $this->createStub(ActivityRepository::class),
            notificationService: $notificationServiceMock,
            messageFactory: $messageFactoryMock,
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
        );

        // Act
        $subject->log($type, $user, $meta);

        // Assert
    }

    public function testGetUserList(): void
    {
        // Arrange
        $user = $this->createStub(User::class);

        // Arrange
        $activity1 = $this->createMock(Activity::class);
        $activity1->expects($this->once())->method('setMessage')->with('Message 1')->willReturn($activity1);

        $activity2 = $this->createMock(Activity::class);
        $activity2->expects($this->once())->method('setMessage')->with('Message 2')->willReturn($activity2);

        $activities = [$activity1, $activity2];

        // Arrange
        $repoMock = $this->createMock(ActivityRepository::class);
        $repoMock->expects($this->once())->method('getUserDisplay')->with($user)->willReturn($activities);

        // Arrange
        $message1 = $this->createMock(MessageInterface::class);
        $message1->expects($this->once())->method('render')->with(true)->willReturn('Message 1');

        $message2 = $this->createMock(MessageInterface::class);
        $message2->expects($this->once())->method('render')->with(true)->willReturn('Message 2');

        // Arrange
        $messageFactoryMock = $this->createMock(MessageFactory::class);
        $messageFactoryMock->expects($this->exactly(2))->method('build')->willReturnOnConsecutiveCalls($message1, $message2);

        // Arrange
        $subject = new ActivityService(
            em: $this->createStub(EntityManagerInterface::class),
            repo: $repoMock,
            notificationService: $this->createStub(ActivityNotificationService::class),
            messageFactory: $messageFactoryMock,
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
        );

        // Act
        $result = $subject->getUserList($user);

        // Assert
        static::assertSame($activities, $result);
    }

    public function testGetAdminList(): void
    {
        // Arrange
        $activity1 = $this->createMock(Activity::class);
        $activity1->expects($this->once())->method('setMessage')->with('Message 1')->willReturn($activity1);

        $activity2 = $this->createMock(Activity::class);
        $activity2->expects($this->once())->method('setMessage')->with('Message 2')->willReturn($activity2);

        $activities = [$activity1, $activity2];

        // Arrange
        $repoMock = $this->createMock(ActivityRepository::class);
        $repoMock->expects($this->once())->method('findRecentForAdmin')->with(250, null, null)->willReturn($activities);

        // Arrange
        $message1 = $this->createMock(MessageInterface::class);
        $message1->expects($this->once())->method('render')->with(false)->willReturn('Message 1');

        $message2 = $this->createMock(MessageInterface::class);
        $message2->expects($this->once())->method('render')->with(false)->willReturn('Message 2');

        // Arrange
        $messageFactoryMock = $this->createMock(MessageFactory::class);
        $messageFactoryMock->expects($this->exactly(2))->method('build')->willReturnOnConsecutiveCalls($message1, $message2);

        // Arrange
        $subject = new ActivityService(
            em: $this->createStub(EntityManagerInterface::class),
            repo: $repoMock,
            notificationService: $this->createStub(ActivityNotificationService::class),
            messageFactory: $messageFactoryMock,
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
        );

        // Act
        $result = $subject->getAdminList();

        // Assert
        static::assertSame($activities, $result);
    }

    public function testGetAdminDetailReturnsActivityWithHtmlMessage(): void
    {
        // Arrange
        $activity = $this->createMock(Activity::class);
        $activity->expects($this->once())->method('setMessage')->with('<b>HTML</b>')->willReturn($activity);

        // Arrange
        $repoMock = $this->createMock(ActivityRepository::class);
        $repoMock->expects($this->once())->method('find')->with(42)->willReturn($activity);

        // Arrange
        $messageMock = $this->createMock(MessageInterface::class);
        $messageMock->expects($this->once())->method('render')->with(true)->willReturn('<b>HTML</b>');

        // Arrange
        $messageFactoryMock = $this->createMock(MessageFactory::class);
        $messageFactoryMock->expects($this->once())->method('build')->with($activity)->willReturn($messageMock);

        // Arrange
        $subject = new ActivityService(
            em: $this->createStub(EntityManagerInterface::class),
            repo: $repoMock,
            notificationService: $this->createStub(ActivityNotificationService::class),
            messageFactory: $messageFactoryMock,
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
        );

        // Act
        $result = $subject->getAdminDetail(42);

        // Assert
        static::assertSame($activity, $result);
    }

    public function testGetAdminDetailReturnsNullWhenNotFound(): void
    {
        // Arrange
        $repoMock = $this->createMock(ActivityRepository::class);
        $repoMock->expects($this->once())->method('find')->with(99)->willReturn(null);

        // Arrange
        $subject = new ActivityService(
            em: $this->createStub(EntityManagerInterface::class),
            repo: $repoMock,
            notificationService: $this->createStub(ActivityNotificationService::class),
            messageFactory: $this->createStub(MessageFactory::class),
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
        );

        // Act
        $result = $subject->getAdminDetail(99);

        // Assert
        static::assertNull($result);
    }
}
