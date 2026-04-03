<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminMemberApproved;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class AdminMemberApprovedTest extends TestCase
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
        // Arrange
        $userId = 42;
        $userName = 'JohnDoe';
        $meta = ['user_id' => $userId];
        $userNames = [$userId => $userName];

        $subject = new AdminMemberApproved();
        $subject->injectServices($this->router, $this->imageService, $meta, $userNames);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminMemberApproved::TYPE, $subject->getType());
        static::assertEquals('Approved member: JohnDoe', $subject->render());
        static::assertEquals('Approved member: JohnDoe', $subject->render(true));
    }

    public function testRendersDeletedUserGracefully(): void
    {
        // Arrange
        $subject = new AdminMemberApproved();
        $subject->injectServices($this->router, $this->imageService, ['user_id' => 99], []);

        // Act & Assert
        static::assertSame('Approved member: [deleted]', $subject->render());
    }

    public function testCanCatchMissingUserId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'user_id' in meta in core.admin_member_approved"));

        $subject = new AdminMemberApproved();
        $subject->injectServices($this->router, $this->imageService, []);

        // Act
        $subject->validate();
    }

    public function testCanCatchNonNumericUserId(): void
    {
        // Arrange
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'user_id' has to be numeric in 'core.admin_member_approved'"),
        );

        $subject = new AdminMemberApproved();
        $subject->injectServices($this->router, $this->imageService, ['user_id' => 'not-a-number']);

        // Act
        $subject->validate();
    }
}
