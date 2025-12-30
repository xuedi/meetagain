<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\Registered;
use App\Service\ImageHtmlRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class RegisteredTest extends TestCase
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
        $expectedText = 'User registered';
        $expectedHtml = 'User registered';

        $subject = new Registered();
        $subject->injectServices($this->router, $this->imageService);

        // check returns
        $this->assertInstanceOf(MessageInterface::class, $subject->validate());
        $this->assertEquals(ActivityType::Registered, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }
}
