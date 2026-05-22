<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\UpdatedProfilePicture;
use App\Service\Media\ImageHtmlRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class UpdatedProfilePictureTest extends TestCase
{
    private MockObject|RouterInterface $router;
    private MockObject|ImageHtmlRenderer $imageRenderer;
    private IdentityTranslator $translator;

    public function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        // keep ImageHtmlRenderer as a mock because interaction is asserted
        $this->imageRenderer = $this->createMock(ImageHtmlRenderer::class);
        $this->translator = new IdentityTranslator();
    }

    public function testCanBuild(): void
    {
        $expectedText = 'profile_social.activity_updated_profile_picture';
        $oldImageHtml = '<img src="old-image.jpg" alt="Old Image">';
        $newImageHtml = '<img src="new-image.jpg" alt="New Image">';
        $expectedHtml =
            'profile_social.activity_updated_profile_picture<div class="is-pulled-top-right">'
            . $oldImageHtml
            . '<i class="fa-solid fa-arrow-right"></i>'
            . $newImageHtml
            . '</div>';
        $meta = ['old' => 0, 'new' => 1];

        // Set up expectations for renderThumbnail
        $this->imageRenderer
            ->expects($this->exactly(2))
            ->method('renderThumbnail')
            ->willReturnMap([
                [0, '50x50', $oldImageHtml],
                [1, '50x50', $newImageHtml],
            ]);

        $subject = new UpdatedProfilePicture()->injectServices($this->router, $this->imageRenderer, $this->translator, $meta);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(UpdatedProfilePicture::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }
}
