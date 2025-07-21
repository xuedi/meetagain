<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\Messages\Registered;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use App\Service\ImageService;

class RegisteredTest extends TestCase
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
        $expectedText = 'User registered';
        $expectedHtml = 'User registered';

        $subject = new Registered();
        $subject->injectServices($this->router, $this->imageService);

        // check returns
        $this->assertTrue($subject->validate());
        $this->assertEquals(ActivityType::Registered, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }
}