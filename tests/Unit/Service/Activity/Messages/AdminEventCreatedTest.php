<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Enum\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\AdminEventCreated;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class AdminEventCreatedTest extends TestCase
{
    private RouterInterface $router;
    private ImageHtmlRenderer $imageService;

    public function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->imageService = $this->createStub(ImageHtmlRenderer::class);
    }

    public function testCanBuild(): void
    {
        // Arrange
        $eventId = 42;
        $eventName = 'Test Event';
        $meta = ['event_id' => $eventId];
        $eventNames = [$eventId => $eventName];

        $subject = new AdminEventCreated();
        $subject->injectServices($this->router, $this->imageService, $meta, [], $eventNames);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(ActivityType::AdminEventCreated, $subject->getType());
        static::assertEquals('Created event: Test Event', $subject->render());
        static::assertEquals('Created event: Test Event', $subject->render(true));
    }

    public function testRendersDeletedEventGracefully(): void
    {
        // Arrange
        $subject = new AdminEventCreated();
        $subject->injectServices($this->router, $this->imageService, ['event_id' => 99], [], []);

        // Act & Assert
        static::assertSame('Created event: [deleted]', $subject->render());
    }

    public function testCanCatchMissingEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in AdminEventCreated"));

        $subject = new AdminEventCreated();
        $subject->injectServices($this->router, $this->imageService, []);

        // Act
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'event_id' has to be numeric in 'AdminEventCreated'"),
        );

        $subject = new AdminEventCreated();
        $subject->injectServices($this->router, $this->imageService, ['event_id' => 'not-a-number']);

        // Act
        $subject->validate();
    }
}
