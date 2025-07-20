<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\Messages\RsvpNo;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class RsvpNoTest extends TestCase
{
    private MockObject|RouterInterface $router;

    public function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
    }

    public function testCanBuild(): void
    {
        $eventId = 42;
        $eventName = 'Test Event';
        $expectedText = 'Is skipping event: Test Event';
        $expectedHtml = 'Is skipping event: Test Event';

        $meta = ['event_id' => $eventId];
        $eventNames = [$eventId => $eventName];

        $subject = new RsvpNo();
        $subject->injectServices($this->router, $meta, [], $eventNames);

        // check returns
        $this->assertTrue($subject->validate());
        $this->assertEquals(ActivityType::RsvpNo, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingEventId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Missing 'event_id' in meta in RsvpNo")
        );

        $subject = new RsvpNo();
        $subject->injectServices($this->router, []);
        $subject->validate();
    }

    public function testCanCatchNonNumericEventId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'event_id' has to be numeric in 'RsvpNo'")
        );

        $subject = new RsvpNo();
        $subject->injectServices($this->router, ['event_id' => 'not-a-number']);
        $subject->validate();
    }
}