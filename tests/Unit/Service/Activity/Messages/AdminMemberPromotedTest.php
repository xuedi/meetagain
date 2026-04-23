<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminMemberPromoted;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class AdminMemberPromotedTest extends TestCase
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
        $meta = ['user_id' => $userId];
        $userNames = [$userId => $userName];

        $subject = new AdminMemberPromoted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta, $userNames);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminMemberPromoted::TYPE, $subject->getType());
        static::assertEquals('profile_social.activity_admin_member_promoted', $subject->render());
        static::assertEquals('profile_social.activity_admin_member_promoted', $subject->render(true));
    }

    public function testRendersDeletedUserGracefully(): void
    {
        // Arrange
        $subject = new AdminMemberPromoted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['user_id' => 99], []);

        // Act & Assert
        static::assertSame('profile_social.activity_admin_member_promoted_deleted', $subject->render());
    }

    public function testCanCatchMissingUserId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'user_id' in meta in core.admin_member_promoted"));

        $subject = new AdminMemberPromoted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, []);

        // Act
        $subject->validate();
    }

    public function testCanCatchNonNumericUserId(): void
    {
        // Arrange
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'user_id' has to be numeric in 'core.admin_member_promoted'"),
        );

        $subject = new AdminMemberPromoted();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['user_id' => 'not-a-number']);

        // Act
        $subject->validate();
    }
}
