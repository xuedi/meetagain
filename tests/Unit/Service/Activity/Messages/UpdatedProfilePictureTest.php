<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\Messages\UpdatedProfilePicture;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class UpdatedProfilePictureTest extends TestCase
{
    private MockObject|RouterInterface $router;

    public function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
    }

    public function testCanBuild(): void
    {
        $expectedText = 'User changed their profile picture';
        $expectedHtml = 'User changed their profile picture';
        $meta = ['old' => 0, 'new' => 1];;

        $subject = new UpdatedProfilePicture()->injectServices($this->router, $meta);

        // check returns
        $this->assertTrue($subject->validate());
        $this->assertEquals(ActivityType::UpdatedProfilePicture, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }
}
