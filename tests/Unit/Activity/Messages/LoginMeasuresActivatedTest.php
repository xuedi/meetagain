<?php declare(strict_types=1);

namespace Tests\Unit\Activity\Messages;

use App\Activity\Messages\LoginMeasuresActivated;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class LoginMeasuresActivatedTest extends TestCase
{
    public function testGetType(): void
    {
        static::assertSame('core.login_measures_activated', new LoginMeasuresActivated()->getType());
    }

    public function testRenderHtmlEscapesTheIp(): void
    {
        // Arrange
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['profile_social.activity_login_measures_activated' => '%ip% after %attempts%'], 'en');
        $subject = new LoginMeasuresActivated();
        $subject->injectServices($this->createStub(RouterInterface::class), $this->createStub(ImageHtmlRenderer::class), $translator, [
            'ip' => '<b>1.2.3.4',
            'attempts' => 3,
        ]);

        // Act
        $html = $subject->render(true);

        // Assert
        static::assertSame('&lt;b&gt;1.2.3.4 after 3', $html);
    }

    public function testValidateRequiresTheIpAndAttempts(): void
    {
        // Arrange
        $subject = new LoginMeasuresActivated();
        $subject->injectServices($this->createStub(RouterInterface::class), $this->createStub(ImageHtmlRenderer::class), new IdentityTranslator(), [
            'ip' => '1.2.3.4',
        ]);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $subject->validate();
    }
}
