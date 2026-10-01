<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity;

use App\Activity\MessageFactory;
use App\Activity\MessageInterface;
use App\Activity\Messages\RsvpYes;
use App\Activity\UnknownMessage;
use App\Entity\Activity;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class MessageFactoryTest extends TestCase
{
    private MockObject|RouterInterface $router;
    private MockObject|UserRepository $userRepository;
    private MockObject|EventRepository $eventRepository;
    private MockObject|RequestStack $requestStack;
    private MockObject|MessageInterface $message;
    private MockObject|ImageHtmlRenderer $imageRenderer;
    private MockObject|Activity $activity;
    private array $messages;
    private IdentityTranslator $translator;

    public function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->message = $this->createStub(MessageInterface::class);
        $this->activity = $this->createStub(Activity::class);
        $this->imageRenderer = $this->createStub(ImageHtmlRenderer::class);
        $this->messages = [$this->message];
        $this->translator = new IdentityTranslator();
    }

    public function testBuildReturnsCorrectMessage(): void
    {
        $activityType = 'core.login';
        $meta = ['key' => 'value'];
        $userNames = ['userNames'];
        $eventNames = ['eventNames'];
        $locale = 'en';

        $request = $this->createStub(Request::class);
        $request->method('getLocale')->willReturn($locale);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->activity->method('getMeta')->willReturn($meta);
        $this->activity->method('getType')->willReturn($activityType);
        $this->message->method('getType')->willReturn($activityType);
        $this->message->method('injectServices')->willReturn($this->message);
        $this->userRepository->method('getUserNameList')->willReturn($userNames);
        $this->eventRepository->method('getEventNameList')->willReturn($eventNames);

        $factory = new MessageFactory(
            $this->messages,
            $this->router,
            $this->userRepository,
            $this->eventRepository,
            $this->requestStack,
            $this->imageRenderer,
            $this->translator,
        );

        $result = $factory->build($this->activity);

        // Assert
        static::assertSame($this->message, $result);
    }

    public function testBuildReturnsUnknownActivityMessageWhenNoMatchingMessage(): void
    {
        $activityType = 'core.login';
        $differentType = 'core.changed_username';

        $this->activity->method('getType')->willReturn($activityType);
        $this->message->method('getType')->willReturn($differentType);

        $factory = new MessageFactory(
            $this->messages,
            $this->router,
            $this->userRepository,
            $this->eventRepository,
            $this->requestStack,
            $this->imageRenderer,
            $this->translator,
        );

        $result = $factory->build($this->activity);

        // Assert
        static::assertInstanceOf(UnknownMessage::class, $result);
    }

    public function testEveryBuildResolvesItsOwnTypeAmongSeveralMessages(): void
    {
        // Arrange
        $login = $this->createStub(MessageInterface::class);
        $login->method('getType')->willReturn('core.login');
        $login->method('injectServices')->willReturn($login);
        $rsvp = $this->createStub(MessageInterface::class);
        $rsvp->method('getType')->willReturn('core.rsvp_yes');
        $rsvp->method('injectServices')->willReturn($rsvp);
        $loginActivity = $this->createStub(Activity::class);
        $loginActivity->method('getType')->willReturn('core.login');
        $rsvpActivity = $this->createStub(Activity::class);
        $rsvpActivity->method('getType')->willReturn('core.rsvp_yes');
        $factory = new MessageFactory(
            [$login, $rsvp],
            $this->router,
            $this->userRepository,
            $this->eventRepository,
            $this->requestStack,
            $this->imageRenderer,
            $this->translator,
        );

        // Act
        $first = $factory->build($rsvpActivity);
        $second = $factory->build($loginActivity);

        // Assert
        static::assertSame($rsvp, $first);
        static::assertSame($login, $second);
    }

    public function testValidateChecksTheMetaWithoutLoadingTheNameLists(): void
    {
        // Arrange
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->never())->method('getUserNameList');
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects($this->never())->method('getEventNameList');
        $valid = $this->createStub(Activity::class);
        $valid->method('getType')->willReturn(RsvpYes::TYPE);
        $valid->method('getMeta')->willReturn(['event_id' => 7]);
        $invalid = $this->createStub(Activity::class);
        $invalid->method('getType')->willReturn(RsvpYes::TYPE);
        $invalid->method('getMeta')->willReturn([]);
        $factory = new MessageFactory(
            [new RsvpYes()],
            $this->router,
            $userRepository,
            $eventRepository,
            $this->requestStack,
            $this->imageRenderer,
            $this->translator,
        );

        // Act
        $factory->validate($valid);

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $factory->validate($invalid);
    }
}
