<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\Messages\UpdatedProfilePicture;
use App\Service\ImageService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class UpdatedProfilePictureTest extends TestCase
{
    private MockObject|RouterInterface $router;
    private MockObject|ImageService $imageService;

    public function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        // keep ImageService as a mock because interaction is asserted
        $this->imageService = $this->createMock(ImageService::class);
    }

    public function testCanBuild(): void
    {
        $expectedText = 'User changed their profile picture';
        $oldImageHtml = '<img src="old-image.jpg" alt="Old Image">';
        $newImageHtml = '<img src="new-image.jpg" alt="New Image">';
        $expectedHtml =
            'User changed their profile picture<div class="is-pulled-top-right">' .
            $oldImageHtml .
            '<i class="fa-solid fa-arrow-right"></i>' .
            $newImageHtml .
            '</div>';
        $meta = ['old' => 0, 'new' => 1];

        // Set up expectations for imageTemplateById
        $this->imageService
            ->expects($this->exactly(2))
            ->method('imageTemplateById')
            ->willReturnMap([
                [0, $oldImageHtml],
                [1, $newImageHtml],
            ]);

        $subject = new UpdatedProfilePicture()->injectServices($this->router, $this->imageService, $meta);

        // check returns
        $this->assertTrue($subject->validate());
        $this->assertEquals(ActivityType::UpdatedProfilePicture, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }
}
