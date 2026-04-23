<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\RsvpYes;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class RsvpYesTest extends TestCase
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
        $eventUrl = '/event/42';
        $expectedText = 'profile_social.activity_rsvp_yes';
        $expectedHtml = 'profile_social.activity_rsvp_yes';

        $meta = ['event_id' => $eventId];
        $eventNames = [$eventId => $eventName];

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('app_event_details', ['id' => $eventId])
            ->willReturn($eventUrl);

        $subject = new RsvpYes();
        $subject->injectServices($router, $this->imageService, $this->translator, $meta, [], $eventNames);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(RsvpYes::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testRendersDeletedEventGracefully(): void
    {
        // Arrange
        $meta = ['event_id' => 99];

        $subject = new RsvpYes();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta, [], []);

        // Act & Assert
        static::assertSame('profile_social.activity_rsvp_yes_deleted', $subject->render());
        static::assertSame('profile_social.activity_rsvp_yes_deleted', $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in core.rsvp_yes"));

        $subject = new RsvpYes();
        $subject->injectServices($this->router, $this->imageService, $this->translator, []);
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Value 'event_id' has to be numeric in 'core.rsvp_yes'"));

        $subject = new RsvpYes();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 'not-a-number']);
        $subject->validate();
    }
}
