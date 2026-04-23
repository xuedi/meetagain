<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\Login;
use App\Service\Media\ImageHtmlRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class LoginTest extends TestCase
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
        $expectedText = 'profile_social.activity_login';
        $expectedHtml = 'profile_social.activity_login';

        $subject = new Login()->injectServices($this->router, $this->imageService, $this->translator);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(Login::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }
}
