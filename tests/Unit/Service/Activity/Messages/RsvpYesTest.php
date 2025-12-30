<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\RsvpYes;
use App\Service\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class RsvpYesTest extends TestCase
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
        $eventId = 42;
        $eventName = 'Test Event';
        $expectedText = 'Going to event: Test Event';
        $expectedHtml = 'Going to event: Test Event';

        $meta = ['event_id' => $eventId];
        $eventNames = [$eventId => $eventName];

        $subject = new RsvpYes();
        $subject->injectServices($this->router, $this->imageService, $meta, [], $eventNames);

        // check returns
        $this->assertInstanceOf(MessageInterface::class, $subject->validate());
        $this->assertEquals(ActivityType::RsvpYes, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'event_id' in meta in RsvpYes"));

        $subject = new RsvpYes();
        $subject->injectServices($this->router, $this->imageService, []);
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Value 'event_id' has to be numeric in 'RsvpYes'"));

        $subject = new RsvpYes();
        $subject->injectServices($this->router, $this->imageService, ['event_id' => 'not-a-number']);
        $subject->validate();
    }
}
