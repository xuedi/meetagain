<?php declare(strict_types=1);

namespace Tests\Unit\Activity\Messages;

use App\Activity\Messages\SupportEmailVouched;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class SupportEmailVouchedTest extends TestCase
{
    public function testGetType(): void
    {
        static::assertSame('core.support_email_vouched', new SupportEmailVouched()->getType());
    }

    public function testRenderHtmlLinksTheRequest(): void
    {
        // Arrange
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/admin/support/7');
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['profile_social.activity_support_email_vouched' => 'vouched %request%'], 'en');
        $subject = new SupportEmailVouched();
        $subject->injectServices($router, $this->createStub(ImageHtmlRenderer::class), $translator, ['request_id' => 7]);

        // Act
        $html = $subject->render(true);

        // Assert
        static::assertSame('vouched <a href="/admin/support/7">#7</a>', $html);
    }

    public function testValidateRequiresTheRequestId(): void
    {
        // Arrange
        $subject = new SupportEmailVouched();
        $subject->injectServices($this->createStub(RouterInterface::class), $this->createStub(ImageHtmlRenderer::class), new Translator('en'), []);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $subject->validate();
    }
}
