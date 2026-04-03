<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\ChangedUsername;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class ChangedUsernameTest extends TestCase
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
        $expectedText = 'Changed username from oldName to newName';
        $expectedHtml = 'Changed username from <b>oldName</b> to <b>newName</b>';

        $meta = ['old' => 'oldName', 'new' => 'newName'];

        $subject = new ChangedUsername();
        $subject->injectServices($this->router, $this->imageService, $meta);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(ChangedUsername::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingOld(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'old' in meta in core.changed_username"));

        $subject = new ChangedUsername();
        $subject->injectServices($this->router, $this->imageService, ['new' => 'newName']);
        $subject->validate();
    }

    public function testCanCatchMissingNew(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'new' in meta in core.changed_username"));

        $subject = new ChangedUsername();
        $subject->injectServices($this->router, $this->imageService, ['old' => 'oldName']);
        $subject->validate();
    }
}
