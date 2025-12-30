<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\ChangedUsername;
use App\Service\Activity\Messages\Login;
use App\Service\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
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
        $this->assertInstanceOf(MessageInterface::class, $subject->validate());
        $this->assertEquals(ActivityType::ChangedUsername, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingOld(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'old' in meta in ChangedUsername"));

        $subject = new ChangedUsername();
        $subject->injectServices($this->router, $this->imageService, ['new' => 'newName']);
        $subject->validate();
    }

    public function testCanCatchMissingNew(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'new' in meta in ChangedUsername"));

        $subject = new ChangedUsername();
        $subject->injectServices($this->router, $this->imageService, ['old' => 'oldName']);
        $subject->validate();
    }
}
