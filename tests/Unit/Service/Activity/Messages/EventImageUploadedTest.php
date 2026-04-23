<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\EventImageUploaded;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class EventImageUploadedTest extends TestCase
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
        $imageCount = 3;
        $eventUrl = '/event/42';
        $expectedText = 'profile_social.activity_event_images_uploaded';
        $expectedHtml = 'profile_social.activity_event_images_uploaded';

        $meta = ['event_id' => $eventId, 'images' => $imageCount];
        $eventNames = [$eventId => $eventName];

        // Use a focused local mock for router to assert interaction
        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('app_event_details', ['id' => $eventId])
            ->willReturn($eventUrl);

        $subject = new EventImageUploaded();
        $subject->injectServices($router, $this->imageService, $this->translator, $meta, [], $eventNames);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(EventImageUploaded::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testRendersDeletedEventGracefully(): void
    {
        $meta = ['event_id' => 99, 'images' => 2];

        $subject = new EventImageUploaded();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta, [], []);

        static::assertSame('profile_social.activity_event_images_uploaded_deleted', $subject->render());
        static::assertSame('profile_social.activity_event_images_uploaded_deleted', $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in core.event_image_uploaded"));

        $subject = new EventImageUploaded();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['images' => 3]);
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'event_id' has to be numeric in 'core.event_image_uploaded'"),
        );

        $subject = new EventImageUploaded();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 'not-a-number', 'images' => 3]);
        $subject->validate();
    }

    public function testCanCatchMissingImages(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'images' in meta in core.event_image_uploaded"));

        $subject = new EventImageUploaded();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 42]);
        $subject->validate();
    }

    public function testCanCatchNonNumericImages(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'images' has to be numeric in 'core.event_image_uploaded'"),
        );

        $subject = new EventImageUploaded();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['event_id' => 42, 'images' => 'not-a-number']);
        $subject->validate();
    }
}
