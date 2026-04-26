<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminMemberStatusChanged;
use App\Enum\UserStatus;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class AdminMemberStatusChangedTest extends TestCase
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
        $meta = [
            'user_id' => $userId,
            'old' => UserStatus::Active->value,
            'new' => UserStatus::Blocked->value,
        ];
        $userNames = [$userId => $userName];

        $subject = new AdminMemberStatusChanged();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta, $userNames);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminMemberStatusChanged::TYPE, $subject->getType());
        static::assertNotEmpty($subject->render());
    }

    public function testCanCatchNonNumericOld(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);

        $subject = new AdminMemberStatusChanged();
        $subject->injectServices($this->router, $this->imageService, $this->translator, [
            'user_id' => 1,
            'old' => 'not-a-number',
            'new' => 2,
        ]);

        // Act
        $subject->validate();
    }
}
