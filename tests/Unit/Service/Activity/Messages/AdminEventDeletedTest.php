<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminEventDeleted;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class AdminEventDeletedTest extends TestCase
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
        $meta = ['event_id' => 42, 'event_name' => 'Old Meetup'];

        $subject = new AdminEventDeleted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminEventDeleted::TYPE, $subject->getType());
        static::assertEquals('profile_social.activity_admin_event_deleted', $subject->render());
        static::assertEquals('profile_social.activity_admin_event_deleted', $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in core.admin_event_deleted"));

        $subject = new AdminEventDeleted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_name' => 'Old Meetup']);

        // Act
        $subject->validate();
    }

    public function testCanCatchMissingEventName(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_name' in meta in core.admin_event_deleted"));

        $subject = new AdminEventDeleted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 42]);

        // Act
        $subject->validate();
    }
}
