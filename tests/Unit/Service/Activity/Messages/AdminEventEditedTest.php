<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminEventEdited;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class AdminEventEditedTest extends TestCase
{
    private RouterInterface $router;
    private ImageHtmlRenderer $imageService;
    private IdentityTranslator $translator;

    public function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->imageService = $this->createStub(ImageHtmlRenderer::class);
        $this->translator = new IdentityTranslator();
    }

    public function testCanBuild(): void
    {
        // Arrange
        $eventId = 42;
        $eventName = 'Test Event';
        $meta = ['event_id' => $eventId];
        $eventNames = [$eventId => $eventName];

        $subject = new AdminEventEdited();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta, [], $eventNames);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminEventEdited::TYPE, $subject->getType());
        static::assertEquals('profile_social.activity_admin_event_edited', $subject->render());
        static::assertEquals('profile_social.activity_admin_event_edited', $subject->render(true));
    }

    public function testRendersDeletedEventGracefully(): void
    {
        // Arrange
        $subject = new AdminEventEdited();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 99], [], []);

        // Act & Assert
        static::assertSame('profile_social.activity_admin_event_edited_deleted', $subject->render());
    }

    public function testCanCatchMissingEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in core.admin_event_edited"));

        $subject = new AdminEventEdited();
        $subject->injectServices($this->router, $this->imageService, $this->translator, []);

        // Act
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'event_id' has to be numeric in 'core.admin_event_edited'"),
        );

        $subject = new AdminEventEdited();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 'not-a-number']);

        // Act
        $subject->validate();
    }
}
