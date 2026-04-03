<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Activity\Messages\Login;
use App\Entity\Activity;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\Activity\ActivityService;
use App\Service\Activity\MessageFactory;
use App\Activity\MessageInterface;
use App\Service\Activity\NotificationService as ActivityNotificationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ActivityServiceTest extends TestCase
{
    public function testLog(): void
    {
        // Arrange: prepare test data
        $type = Login::TYPE;
        $user = $this->createStub(User::class);
        $meta = ['key' => 'value'];

        // Arrange: mock MessageInterface to verify validation is called
        $messageMock = $this->createMock(MessageInterface::class);
        $messageMock->expects($this->once())->method('validate');

        // Arrange: mock MessageFactory to return the message mock
        $messageFactoryMock = $this->createMock(MessageFactory::class);
        $messageFactoryMock->expects($this->once())->method('build')->willReturn($messageMock);

        // Arrange: mock NotificationService to verify notification is sent
        $notificationServiceMock = $this->createMock(ActivityNotificationService::class);
        $notificationServiceMock->expects($this->once())->method('notify');

        // Arrange: mock EntityManager to verify Activity is persisted with correct data
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

        // Arrange: create subject with mocked dependencies
        $subject = new ActivityService(
            em: $entityManagerMock,
            repo: $this->createStub(ActivityRepository::class),
            notificationService: $notificationServiceMock,
            messageFactory: $messageFactoryMock,
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
        );

        // Act: log the activity
        $subject->log($type, $user, $meta);

        // Assert: expectations are verified automatically by PHPUnit
    }

    public function testGetUserList(): void
    {
        // Arrange: create user stub
        $user = $this->createStub(User::class);

        // Arrange: mock Activity entities that expect setMessage to be called
        $activity1 = $this->createMock(Activity::class);
        $activity1->expects($this->once())->method('setMessage')->with('Message 1')->willReturn($activity1);

        $activity2 = $this->createMock(Activity::class);
        $activity2->expects($this->once())->method('setMessage')->with('Message 2')->willReturn($activity2);

        $activities = [$activity1, $activity2];

        // Arrange: mock repository to return activities for the user
        $repoMock = $this->createMock(ActivityRepository::class);
        $repoMock->expects($this->once())->method('getUserDisplay')->with($user)->willReturn($activities);

        // Arrange: mock MessageInterface instances that render messages for user view (with links)
        $message1 = $this->createMock(MessageInterface::class);
        $message1->expects($this->once())->method('render')->with(true)->willReturn('Message 1');

        $message2 = $this->createMock(MessageInterface::class);
        $message2->expects($this->once())->method('render')->with(true)->willReturn('Message 2');

        // Arrange: mock MessageFactory to build messages for each activity
        $messageFactoryMock = $this->createMock(MessageFactory::class);
        $messageFactoryMock
            ->expects($this->exactly(2))
            ->method('build')
            ->willReturnOnConsecutiveCalls($message1, $message2);

        // Arrange: create subject with mocked dependencies
        $subject = new ActivityService(
            em: $this->createStub(EntityManagerInterface::class),
            repo: $repoMock,
            notificationService: $this->createStub(ActivityNotificationService::class),
            messageFactory: $messageFactoryMock,
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
        );

        // Act: get user activity list
        $result = $subject->getUserList($user);

        // Assert: returned activities match expected
        static::assertSame($activities, $result);
    }

    public function testGetAdminList(): void
    {
        // Arrange: mock Activity entities that expect setMessage to be called
        $activity1 = $this->createMock(Activity::class);
        $activity1->expects($this->once())->method('setMessage')->with('Message 1')->willReturn($activity1);

        $activity2 = $this->createMock(Activity::class);
        $activity2->expects($this->once())->method('setMessage')->with('Message 2')->willReturn($activity2);

        $activities = [$activity1, $activity2];

        // Arrange: mock repository to return latest 250 activities sorted by date descending
        $repoMock = $this->createMock(ActivityRepository::class);
        $repoMock
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 250)
            ->willReturn($activities);

        // Arrange: mock MessageInterface instances that render messages for admin view (without links)
        $message1 = $this->createMock(MessageInterface::class);
        $message1->expects($this->once())->method('render')->with(false)->willReturn('Message 1');

        $message2 = $this->createMock(MessageInterface::class);
        $message2->expects($this->once())->method('render')->with(false)->willReturn('Message 2');

        // Arrange: mock MessageFactory to build messages for each activity
        $messageFactoryMock = $this->createMock(MessageFactory::class);
        $messageFactoryMock
            ->expects($this->exactly(2))
            ->method('build')
            ->willReturnOnConsecutiveCalls($message1, $message2);

        // Arrange: create subject with mocked dependencies
        $subject = new ActivityService(
            em: $this->createStub(EntityManagerInterface::class),
            repo: $repoMock,
            notificationService: $this->createStub(ActivityNotificationService::class),
            messageFactory: $messageFactoryMock,
            logger: $this->createStub(LoggerInterface::class),
            enrichers: [],
        );

        // Act: get admin activity list
        $result = $subject->getAdminList();

        // Assert: returned activities match expected
        static::assertSame($activities, $result);
    }

    public function testGetAdminDetailReturnsActivityWithHtmlMessage(): void
    {
        // Arrange: create an Activity stub
        $activity = $this->createMock(Activity::class);
        $activity->expects($this->once())->method('setMessage')->with('<b>HTML</b>')->willReturn($activity);

        // Arrange: mock repository to return the activity by ID
        $repoMock = $this->createMock(ActivityRepository::class);
        $repoMock->expects($this->once())->method('find')->with(42)->willReturn($activity);

        // Arrange: mock MessageInterface to return HTML from render(true)
        $messageMock = $this->createMock(MessageInterface::class);
        $messageMock->expects($this->once())->method('render')->with(true)->willReturn('<b>HTML</b>');

        // Arrange: mock MessageFactory to build the message
        $messageFactoryMock = $this->createMock(MessageFactory::class);
        $messageFactoryMock->expects($this->once())->method('build')->with($activity)->willReturn($messageMock);

        // Arrange: create subject with mocked dependencies
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

        // Assert: the activity is returned with its HTML message set
        static::assertSame($activity, $result);
    }

    public function testGetAdminDetailReturnsNullWhenNotFound(): void
    {
        // Arrange: mock repository to return null
        $repoMock = $this->createMock(ActivityRepository::class);
        $repoMock->expects($this->once())->method('find')->with(99)->willReturn(null);

        // Arrange: create subject with mocked dependencies
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

        // Assert: null is returned for a missing activity
        static::assertNull($result);
    }
}
