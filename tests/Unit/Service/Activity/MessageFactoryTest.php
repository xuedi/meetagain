<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Activity\MessageFactory;
use App\Service\Activity\MessageInterface;
use App\Service\GlobalService;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class MessageFactoryTest extends TestCase
{
    private MockObject|RouterInterface $router;
    private MockObject|UserRepository $userRepository;
    private MockObject|EventRepository $eventRepository;
    private MockObject|GlobalService $globalService;
    private MockObject|MessageInterface $message;
    private MockObject|Activity $activity;
    private array $messages;

    public function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->eventRepository = $this->createMock(EventRepository::class);
        $this->globalService = $this->createMock(GlobalService::class);
        $this->message = $this->createMock(MessageInterface::class);
        $this->activity = $this->createMock(Activity::class);
        $this->messages = [$this->message];
    }

    public function testBuildReturnsCorrectMessage(): void
    {
        // Configure mocks
        $activityType = ActivityType::Login;
        $meta = ['key' => 'value'];
        $userNames = ['userNames'];
        $eventNames = ['eventNames'];
        $locale = 'en';

        $this->activity->method('getMeta')->willReturn($meta);
        $this->activity->method('getType')->willReturn($activityType);
        $this->message->method('getType')->willReturn($activityType);
        $this->message->method('injectServices')->willReturn($this->message);
        $this->userRepository->method('getUserNameList')->willReturn($userNames);
        $this->eventRepository->method('getEventNameList')->willReturn($eventNames);
        $this->globalService->method('getCurrentLocale')->willReturn($locale);

        // Create factory with mocked messages
        $factory = new MessageFactory(
            $this->messages,
            $this->router,
            $this->userRepository,
            $this->eventRepository,
            $this->globalService
        );

        // Call the method under test
        $result = $factory->build($this->activity);

        // Assert the result
        $this->assertSame($this->message, $result);
    }

    public function testBuildThrowsExceptionWhenNoMatchingMessage(): void
    {
        // Configure mocks
        $activityType = ActivityType::Login;
        $differentType = ActivityType::ChangedUsername;

        $this->activity->method('getType')->willReturn($activityType);
        $this->message->method('getType')->willReturn($differentType);

        // Create factory with mocked messages
        $factory = new MessageFactory(
            $this->messages,
            $this->router,
            $this->userRepository,
            $this->eventRepository,
            $this->globalService
        );

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cound not find message for activity type: ' . $activityType->name);

        // Call the method under test
        $factory->build($this->activity);
    }
}