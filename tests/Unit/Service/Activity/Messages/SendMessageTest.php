<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\SendMessage;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class SendMessageTest extends TestCase
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
        $userId = 42;
        $userName = 'testUser';
        $expectedText = 'profile_social.activity_send_message';
        $expectedHtml = 'profile_social.activity_send_message';

        $meta = ['user_id' => $userId];
        $userNames = [$userId => $userName];

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('app_member_view', ['id' => $userId])
            ->willReturn('/members/view/42');

        $subject = new SendMessage();
        $subject->injectServices($router, $this->imageService, $this->translator, $meta, $userNames);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(SendMessage::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testRendersDeletedUserGracefully(): void
    {
        $meta = ['user_id' => 99];

        $subject = new SendMessage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta, []);

        static::assertSame('profile_social.activity_send_message_deleted', $subject->render());
        static::assertSame('profile_social.activity_send_message_deleted', $subject->render(true));
    }

    public function testCanCatchMissingUserId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'user_id' in meta in core.send_message"));

        $subject = new SendMessage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, []);
        $subject->validate();
    }

    public function testCanCatchNonNumericUserId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'user_id' has to be numeric in 'core.send_message'"),
        );

        $subject = new SendMessage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['user_id' => 'not-a-number']);
        $subject->validate();
    }
}
