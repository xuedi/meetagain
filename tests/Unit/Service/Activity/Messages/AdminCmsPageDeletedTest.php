<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Enum\ActivityType;
use App\Service\Activity\MessageInterface;
use App\Service\Activity\Messages\AdminCmsPageDeleted;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class AdminCmsPageDeletedTest extends TestCase
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
        $meta = ['cms_id' => 5, 'cms_slug' => 'old-page'];

        $subject = new AdminCmsPageDeleted();
        $subject->injectServices($this->router, $this->imageService, $meta);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(ActivityType::AdminCmsPageDeleted, $subject->getType());
        static::assertEquals('Deleted CMS page: old-page', $subject->render());
        static::assertEquals('Deleted CMS page: old-page', $subject->render(true));
    }

    public function testCanCatchMissingCmsId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'cms_id' in meta in AdminCmsPageDeleted"));

        $subject = new AdminCmsPageDeleted();
        $subject->injectServices($this->router, $this->imageService, ['cms_slug' => 'old-page']);

        // Act
        $subject->validate();
    }

    public function testCanCatchMissingCmsSlug(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'cms_slug' in meta in AdminCmsPageDeleted"));

        $subject = new AdminCmsPageDeleted();
        $subject->injectServices($this->router, $this->imageService, ['cms_id' => 5]);

        // Act
        $subject->validate();
    }
}
