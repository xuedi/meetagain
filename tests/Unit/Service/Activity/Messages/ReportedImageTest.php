<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Enum\ImageReportReason;
use App\Activity\MessageInterface;
use App\Activity\Messages\ReportedImage;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class ReportedImageTest extends TestCase
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
        $imageId = 42;
        $reason = ImageReportReason::Privacy->value;
        $expectedText = 'profile_social.activity_reported_image';
        $expectedHtml = 'profile_social.activity_reported_image';

        $meta = ['image_id' => $imageId, 'reason' => $reason];

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(ReportedImage::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanBuildWithDifferentReason(): void
    {
        $imageId = 42;
        $reason = ImageReportReason::Inappropriate->value;
        $expectedText = 'profile_social.activity_reported_image';
        $expectedHtml = 'profile_social.activity_reported_image';

        $meta = ['image_id' => $imageId, 'reason' => $reason];

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta);

        // check returns
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(ReportedImage::TYPE, $subject->getType());
        static::assertEquals($expectedText, $subject->render());
        static::assertEquals($expectedHtml, $subject->render(true));
    }

    public function testCanCatchMissingImageId(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'image_id' in meta in core.reported_image"));

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['reason' => ImageReportReason::Privacy->value]);
        $subject->validate();
    }

    public function testCanCatchNonNumericImageId(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'image_id' has to be numeric in 'core.reported_image'"),
        );

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, [
            'image_id' => 'not-a-number',
            'reason' => ImageReportReason::Privacy->value,
        ]);
        $subject->validate();
    }

    public function testCanCatchMissingReason(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'reason' in meta in core.reported_image"));

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['image_id' => 42]);
        $subject->validate();
    }

    public function testCanCatchNonNumericReason(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException("Value 'reason' has to be numeric in 'core.reported_image'"),
        );

        $subject = new ReportedImage();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['image_id' => 42, 'reason' => 'not-a-number']);
        $subject->validate();
    }
}
