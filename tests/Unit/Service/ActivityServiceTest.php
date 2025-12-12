<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Service\Activity\MessageFactory;
use App\Service\Activity\MessageInterface;
use App\Service\ActivityService;
use App\Service\NotificationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ActivityServiceTest extends TestCase
{
    private MockObject|EntityManagerInterface $emMock;
    private MockObject|ActivityRepository $activityRepoMock;
    private MockObject|NotificationService $notificationServiceMock;
    private MockObject|MessageFactory $messageFactoryMock;
    private ActivityService $subject;

    protected function setUp(): void
    {
        // Default to stubs to avoid PHPUnit notices for mocks without expectations.
        $this->emMock = $this->createStub(EntityManagerInterface::class);
        $this->activityRepoMock = $this->createStub(ActivityRepository::class);
        $this->notificationServiceMock = $this->createStub(NotificationService::class);
        $this->messageFactoryMock = $this->createStub(MessageFactory::class);
        $this->subject = new ActivityService(
            em: $this->emMock,
            repo: $this->activityRepoMock,
            notificationService: $this->notificationServiceMock,
            messageFactory: $this->messageFactoryMock,
        );
    }

    public function testLog(): void
    {
        // Test data
        $type = ActivityType::Login;
        $user = $this->createStub(User::class);
        $meta = ['key' => 'value'];

        // Mock message
        $messageMock = $this->createMock(MessageInterface::class);
        $messageMock->expects($this->once())->method('validate');

        // Mock message factory
        $messageFactory = $this->createMock(MessageFactory::class);
        $messageFactory
            ->expects($this->once())
            ->method('build')
            ->willReturn($messageMock);

        // Mock notification service
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())->method('notify');

        // Mock entity manager
        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects($this->once())
            ->method('persist')
            ->with(
                $this->callback(function (Activity $activity) use ($type, $user, $meta) {
                    return (
                        $activity->getType() === $type &&
                        $activity->getUser() === $user &&
                        $activity->getMeta() === $meta &&
                        $activity->getCreatedAt() instanceof DateTimeImmutable
                    );
                })
            );

        $em->expects($this->once())->method('flush');

        // Call the method
        $subject = new ActivityService(
            em: $em,
            repo: $this->createStub(ActivityRepository::class),
            notificationService: $notificationService,
            messageFactory: $messageFactory,
        );

        $subject->log($type, $user, $meta);
    }

    public function testGetUserList(): void
    {
        // Test data
        $user = $this->createStub(User::class);
        $activities = [
            $this->createMock(Activity::class),
            $this->createMock(Activity::class),
        ];

        // Mock repository
        $repo = $this->createMock(ActivityRepository::class);
        $repo
            ->expects($this->once())
            ->method('getUserDisplay')
            ->with($user)
            ->willReturn($activities);

        // Mock message factory for each activity
        $message1 = $this->createMock(MessageInterface::class);
        $message1->expects($this->once())->method('render')->with(true)->willReturn('Message 1');

        $message2 = $this->createMock(MessageInterface::class);
        $message2->expects($this->once())->method('render')->with(true)->willReturn('Message 2');

        $messageFactory = $this->createMock(MessageFactory::class);
        $messageFactory->expects($this->exactly(2))->method('build')->willReturnOnConsecutiveCalls(
            $message1,
            $message2,
        );

        // Mock activities to set message and return themselves
        $activities[0]
            ->expects($this->once())
            ->method('setMessage')
            ->with('Message 1')
            ->willReturn($activities[0]);

        $activities[1]
            ->expects($this->once())
            ->method('setMessage')
            ->with('Message 2')
            ->willReturn($activities[1]);

        // Call the method
        $subject = new ActivityService(
            em: $this->createStub(EntityManagerInterface::class),
            repo: $repo,
            notificationService: $this->createStub(NotificationService::class),
            messageFactory: $messageFactory,
        );

        $result = $subject->getUserList($user);

        // Assert result
        $this->assertSame($activities, $result);
    }

    public function testGetAdminList(): void
    {
        // Test data
        $activities = [
            $this->createMock(Activity::class),
            $this->createMock(Activity::class),
        ];

        // Mock repository
        $repo = $this->createMock(ActivityRepository::class);
        $repo
            ->expects($this->once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 250)
            ->willReturn($activities);

        // Mock message factory for each activity
        $message1 = $this->createMock(MessageInterface::class);
        $message1->expects($this->once())->method('render')->with(false)->willReturn('Message 1');

        $message2 = $this->createMock(MessageInterface::class);
        $message2->expects($this->once())->method('render')->with(false)->willReturn('Message 2');

        $messageFactory = $this->createMock(MessageFactory::class);
        $messageFactory->expects($this->exactly(2))->method('build')->willReturnOnConsecutiveCalls(
            $message1,
            $message2,
        );

        // Mock activities to set message and return themselves
        $activities[0]
            ->expects($this->once())
            ->method('setMessage')
            ->with('Message 1')
            ->willReturn($activities[0]);

        $activities[1]
            ->expects($this->once())
            ->method('setMessage')
            ->with('Message 2')
            ->willReturn($activities[1]);

        // Call the method
        $subject = new ActivityService(
            em: $this->createStub(EntityManagerInterface::class),
            repo: $repo,
            notificationService: $this->createStub(NotificationService::class),
            messageFactory: $messageFactory,
        );

        $result = $subject->getAdminList();

        // Assert result
        $this->assertSame($activities, $result);
    }
}
