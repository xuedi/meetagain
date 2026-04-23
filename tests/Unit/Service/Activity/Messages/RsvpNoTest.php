<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\RsvpNo;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class RsvpNoTest extends TestCase
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
        $eventId = 42;
        $eventName = 'Test Event';
        $expectedText = 'profile_social.activity_rsvp_no';
        $expectedHtml = 'profile_social.activity_rsvp_no';

        $meta = ['event_id' => $eventId];
        $eventNames = [$eventId => $eventName];

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('app_event_details', ['id' => $eventId])
            ->willReturn('/event/42');

        $subject = new RsvpNo();
        $subject->injectServices($router, $this->imageService, $this->translator, $meta, [], $eventNames);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(RsvpNo::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testRendersDeletedEventGracefully(): void
    {
        $meta = ['event_id' => 99];

        $subject = new RsvpNo();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta, [], []);

        static::assertSame('profile_social.activity_rsvp_no_deleted', $subject->render());
        static::assertSame('profile_social.activity_rsvp_no_deleted', $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in core.rsvp_no"));

        $subject = new RsvpNo();
        $subject->injectServices($this->router, $this->imageService, $this->translator, []);
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Value 'event_id' has to be numeric in 'core.rsvp_no'"));

        $subject = new RsvpNo();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 'not-a-number']);
        $subject->validate();
    }
}
