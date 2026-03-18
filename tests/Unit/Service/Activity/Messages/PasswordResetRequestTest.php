<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\PasswordResetRequest;
use App\Service\Media\ImageHtmlRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class PasswordResetRequestTest extends TestCase
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
        $expectedText = 'Requested password reset';
        $expectedHtml = 'Requested password reset';

        $subject = new PasswordResetRequest();
        $subject->injectServices($this->router, $this->imageService);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(ActivityType::PasswordResetRequest, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }
}
