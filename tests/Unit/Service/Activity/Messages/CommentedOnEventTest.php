<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Enum\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\CommentedOnEvent;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class CommentedOnEventTest extends TestCase
{
    private RouterInterface $router;
    private ImageHtmlRenderer $imageService;

    public function setUp(): void
    {
        // Arrange (shared stubs)
        $this->router = $this->createStub(RouterInterface::class);
        $this->imageService = $this->createStub(ImageHtmlRenderer::class);
    }

    public function testCanBuild(): void
    {
        // Arrange
        $eventId = 42;
        $eventName = 'Test Event';
        $eventUrl = '/event/42';
        $expectedText = 'commented on event: Test Event';
        $expectedHtml = 'commented on event <a href="/event/42">Test Event</a>';

        $meta = ['event_id' => $eventId];
        $eventNames = [$eventId => $eventName];

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('app_event_details', ['id' => $eventId])
            ->willReturn($eventUrl);

        $subject = new CommentedOnEvent();
        $subject->injectServices($router, $this->imageService, $meta, [], $eventNames);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(ActivityType::CommentedOnEvent, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testRendersDeletedEventGracefully(): void
    {
        // Arrange
        $meta = ['event_id' => 99];

        $subject = new CommentedOnEvent();
        $subject->injectServices($this->router, $this->imageService, $meta, [], []);

        // Act & Assert
        static::assertSame('commented on event: [deleted]', $subject->render());
        static::assertSame('commented on event [deleted]', $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in CommentedOnEvent"));

        $subject = new CommentedOnEvent();
        $subject->injectServices($this->router, $this->imageService, []);

        // Act
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'event_id' has to be numeric in 'CommentedOnEvent'"),
        );

        $subject = new CommentedOnEvent();
        $subject->injectServices($this->router, $this->imageService, ['event_id' => 'not-a-number']);

        // Act
        $subject->validate();
    }
}
