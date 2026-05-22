<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminMemberDemoted;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class AdminMemberDemotedTest extends TestCase
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
        // Arrange
        $userId = 42;
        $userName = 'JohnDoe';
        $subject = new AdminMemberDemoted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['user_id' => $userId], [$userId => $userName]);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminMemberDemoted::TYPE, $subject->getType());
        static::assertSame('profile_social.activity_admin_member_demoted', $subject->render());
    }

    public function testCanCatchMissingUserId(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);

        $subject = new AdminMemberDemoted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, []);

        // Act
        $subject->validate();
    }
}
