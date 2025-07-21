<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\Messages\Login;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use App\Service\ImageService;

class LoginTest extends TestCase
{
    private MockObject|RouterInterface $router;
    private MockObject|ImageService $imageService;

    public function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->imageService = $this->createMock(ImageService::class);
    }

    public function testCanBuild(): void
    {
        $expectedText = 'User logged in';
        $expectedHtml = 'User logged in';

        $subject = new Login()->injectServices($this->router, $this->imageService);

        // check returns
        $this->assertTrue($subject->validate());
        $this->assertEquals(ActivityType::Login, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }
}
