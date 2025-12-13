<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Entity\ImageReported;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\ReportedImage;
use App\Service\ImageService;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class ReportedImageTest extends TestCase
{
    private MockObject|RouterInterface $router;
    private MockObject|ImageService $imageService;

    public function setUp(): void
    {
        $this->router = $this->createStub(RouterInterface::class);
        $this->imageService = $this->createStub(ImageService::class);
    }

    public function testCanBuild(): void
    {
        $imageId = 42;
        $reason = ImageReported::Privacy->value; // Using Privacy reason (value 1)
        $expectedText = 'Reported image for reason: Privacy';
        $expectedHtml = 'Reported image for reason: <b>Privacy</b>';

        $meta = ['image_id' => $imageId, 'reason' => $reason];

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, $meta);

        // check returns
        $this->assertInstanceOf(MessageInterface::class, $subject->validate());
        $this->assertEquals(ActivityType::ReportedImage, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanBuildWithDifferentReason(): void
    {
        $imageId = 42;
        $reason = ImageReported::Inappropriate->value; // Using Inappropriate reason (value 3)
        $expectedText = 'Reported image for reason: Inappropriate';
        $expectedHtml = 'Reported image for reason: <b>Inappropriate</b>';

        $meta = ['image_id' => $imageId, 'reason' => $reason];

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, $meta);

        // check returns
        $this->assertInstanceOf(MessageInterface::class, $subject->validate());
        $this->assertEquals(ActivityType::ReportedImage, $subject->getType());
        $this->assertEquals($expectedText, $subject->render());
        $this->assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingImageId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'image_id' in meta in ReportedImage"));

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, ['reason' => ImageReported::Privacy->value]);
        $subject->validate();
    }

    public function testCanCatchNonNumericImageId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'image_id' has to be numeric in 'ReportedImage'"),
        );

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, [
            'image_id' => 'not-a-number',
            'reason' => ImageReported::Privacy->value,
        ]);
        $subject->validate();
    }

    public function testCanCatchMissingReason(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'reason' in meta in ReportedImage"));

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, ['image_id' => 42]);
        $subject->validate();
    }

    public function testCanCatchNonNumericReason(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'reason' has to be numeric in 'ReportedImage'"),
        );

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, ['image_id' => 42, 'reason' => 'not-a-number']);
        $subject->validate();
    }
}
