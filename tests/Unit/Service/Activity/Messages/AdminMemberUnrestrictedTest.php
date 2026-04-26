<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminMemberUnrestricted;
use App\Service\Media\ImageHtmlRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class AdminMemberUnrestrictedTest extends TestCase
{
    public function testCanBuild(): void
    {
        // Arrange
        $router = $this->createStub(RouterInterface::class);
        $imageService = $this->createStub(ImageHtmlRenderer::class);
        $translator = new IdentityTranslator();

        $subject = new AdminMemberUnrestricted();
        $subject->injectServices($router, $imageService, $translator, ['user_id' => 1], [1 => 'Jane']);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminMemberUnrestricted::TYPE, $subject->getType());
        static::assertSame('profile_social.activity_admin_member_unrestricted', $subject->render());
    }
}
