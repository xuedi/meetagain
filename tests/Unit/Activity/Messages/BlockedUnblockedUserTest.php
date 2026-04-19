<?php declare(strict_types=1);

namespace Tests\Unit\Activity\Messages;

use App\Activity\Messages\BlockedUser;
use App\Activity\Messages\RegistrationEmailResent;
use App\Activity\Messages\UnblockedUser;
use App\Activity\UnknownActivityMessage;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class BlockedUnblockedUserTest extends TestCase
{
    private RouterInterface $router;
    private ImageHtmlRenderer $imageRenderer;

    protected function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->imageRenderer = $this->createStub(ImageHtmlRenderer::class);
    }

    // =========================================================================
    // BlockedUser
    // =========================================================================

    public function testBlockedUserGetType(): void
    {
        static::assertSame('core.blocked_user', (new BlockedUser())->getType());
    }

    public function testBlockedUserRenderText(): void
    {
        // Arrange
        $subject = new BlockedUser();
        $subject->injectServices($this->router, $this->imageRenderer, ['user_id' => 7], [7 => 'Alice']);

        // Act + Assert
        static::assertSame('Blocked user: Alice', $subject->render());
    }

    public function testBlockedUserRenderHtml(): void
    {
        // Arrange
        $subject = new BlockedUser();
        $subject->injectServices($this->router, $this->imageRenderer, ['user_id' => 7], [7 => 'Alice<']);

        // Act + Assert
        static::assertSame('Blocked user: Alice&lt;', $subject->render(true));
    }

    public function testBlockedUserRenderFallsBackToDeletedWhenUserNameMissing(): void
    {
        // Arrange
        $subject = new BlockedUser();
        $subject->injectServices($this->router, $this->imageRenderer, ['user_id' => 99], []);

        // Act + Assert
        static::assertStringContainsString('[deleted]', $subject->render());
    }

    public function testBlockedUserValidateThrowsWhenUserIdMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $subject = new BlockedUser();
        $subject->injectServices($this->router, $this->imageRenderer, []);
        $subject->validate();
    }

    public function testBlockedUserValidateThrowsWhenUserIdNotNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $subject = new BlockedUser();
        $subject->injectServices($this->router, $this->imageRenderer, ['user_id' => 'nope']);
        $subject->validate();
    }

    // =========================================================================
    // UnblockedUser
    // =========================================================================

    public function testUnblockedUserGetType(): void
    {
        static::assertSame('core.unblocked_user', (new UnblockedUser())->getType());
    }

    public function testUnblockedUserRenderText(): void
    {
        // Arrange
        $subject = new UnblockedUser();
        $subject->injectServices($this->router, $this->imageRenderer, ['user_id' => 3], [3 => 'Bob']);

        // Act + Assert
        static::assertSame('Unblocked user: Bob', $subject->render());
    }

    public function testUnblockedUserRenderHtml(): void
    {
        // Arrange
        $subject = new UnblockedUser();
        $subject->injectServices($this->router, $this->imageRenderer, ['user_id' => 3], [3 => 'Bob>']);

        // Act + Assert
        static::assertSame('Unblocked user: Bob&gt;', $subject->render(true));
    }

    public function testUnblockedUserValidateThrowsWhenUserIdMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $subject = new UnblockedUser();
        $subject->injectServices($this->router, $this->imageRenderer, []);
        $subject->validate();
    }

    // =========================================================================
    // RegistrationEmailResent
    // =========================================================================

    public function testRegistrationEmailResentGetType(): void
    {
        static::assertSame('core.registration_email_resent', (new RegistrationEmailResent())->getType());
    }

    public function testRegistrationEmailResentRender(): void
    {
        // Arrange
        $subject = new RegistrationEmailResent();
        $subject->injectServices($this->router, $this->imageRenderer);

        // Act + Assert
        static::assertSame('Registration email resent by admin', $subject->render());
        static::assertSame('Registration email resent by admin', $subject->render(true));
    }

    // =========================================================================
    // UnknownActivityMessage
    // =========================================================================

    public function testUnknownActivityMessageGetType(): void
    {
        static::assertSame('foo.bar', (new UnknownActivityMessage('foo.bar'))->getType());
    }

    public function testUnknownActivityMessageRenderWithNamespaceAndAction(): void
    {
        // Arrange
        $subject = new UnknownActivityMessage('myplugin.did_something');

        // Act
        $result = $subject->render();

        // Assert
        static::assertStringContainsString('myplugin', $result);
        static::assertStringContainsString('did_something', $result);
        static::assertStringContainsString('plugin inactive', $result);
    }

    public function testUnknownActivityMessageRenderWithNoAction(): void
    {
        // Arrange
        $subject = new UnknownActivityMessage('weird');

        // Act
        $result = $subject->render();

        // Assert
        static::assertStringContainsString('[unknown]', $result);
        static::assertStringContainsString('weird', $result);
        static::assertStringContainsString('plugin inactive', $result);
    }

    public function testUnknownActivityMessageInjectServicesReturnsSelf(): void
    {
        $subject = new UnknownActivityMessage('x.y');
        $result = $subject->injectServices($this->router, $this->imageRenderer);
        static::assertSame($subject, $result);
    }
}
