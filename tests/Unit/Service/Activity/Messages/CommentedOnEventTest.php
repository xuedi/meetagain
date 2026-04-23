<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\CommentedOnEvent;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class CommentedOnEventTest extends TestCase
{
    private RouterInterface $router;
    private ImageHtmlRenderer $imageService;
    private IdentityTranslator $translator;

    public function setUp(): void
    {
        // Arrange (shared stubs)
        $this->router = $this->createStub(RouterInterface::class);
        $this->imageService = $this->createStub(ImageHtmlRenderer::class);
        $this->translator = new IdentityTranslator();
    }

    public function testCanBuild(): void
    {
        // Arrange
        $eventId = 42;
        $eventName = 'Test Event';
        $eventUrl = '/event/42';
        $expectedText = 'profile_social.activity_commented_on_event';
        $expectedHtml = 'profile_social.activity_commented_on_event';

        $meta = ['event_id' => $eventId];
        $eventNames = [$eventId => $eventName];

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('app_event_details', ['id' => $eventId])
            ->willReturn($eventUrl);

        $subject = new CommentedOnEvent();
        $subject->injectServices($router, $this->imageService, $this->translator, $meta, [], $eventNames);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(CommentedOnEvent::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testRendersDeletedEventGracefully(): void
    {
        // Arrange
        $meta = ['event_id' => 99];

        $subject = new CommentedOnEvent();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta, [], []);

        // Act & Assert
        static::assertSame('profile_social.activity_commented_on_event_deleted', $subject->render());
        static::assertSame('profile_social.activity_commented_on_event_deleted', $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in core.commented_on_event"));

        $subject = new CommentedOnEvent();
        $subject->injectServices($this->router, $this->imageService, $this->translator, []);

        // Act
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'event_id' has to be numeric in 'core.commented_on_event'"),
        );

        $subject = new CommentedOnEvent();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 'not-a-number']);

        // Act
        $subject->validate();
    }
}
