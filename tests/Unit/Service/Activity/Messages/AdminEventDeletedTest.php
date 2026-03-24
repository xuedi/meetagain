<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Enum\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\AdminEventDeleted;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class AdminEventDeletedTest extends TestCase
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
        $meta = ['event_id' => 42, 'event_name' => 'Old Meetup'];

        $subject = new AdminEventDeleted();
        $subject->injectServices($this->router, $this->imageService, $meta);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(ActivityType::AdminEventDeleted, $subject->getType());
        static::assertEquals('Deleted event: Old Meetup', $subject->render());
        static::assertEquals('Deleted event: Old Meetup', $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in AdminEventDeleted"));

        $subject = new AdminEventDeleted();
        $subject->injectServices($this->router, $this->imageService, ['event_name' => 'Old Meetup']);

        // Act
        $subject->validate();
    }

    public function testCanCatchMissingEventName(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_name' in meta in AdminEventDeleted"));

        $subject = new AdminEventDeleted();
        $subject->injectServices($this->router, $this->imageService, ['event_id' => 42]);

        // Act
        $subject->validate();
    }
}
