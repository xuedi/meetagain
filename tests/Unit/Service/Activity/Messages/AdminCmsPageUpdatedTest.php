<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminCmsPageUpdated;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

class AdminCmsPageUpdatedTest extends TestCase
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
        $meta = ['cms_id' => 5, 'cms_slug' => 'about'];

        $subject = new AdminCmsPageUpdated();
        $subject->injectServices($this->router, $this->imageService, $meta);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminCmsPageUpdated::TYPE, $subject->getType());
        static::assertEquals('Updated CMS page: about', $subject->render());
        static::assertEquals('Updated CMS page: about', $subject->render(true));
    }

    public function testCanCatchMissingCmsId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'cms_id' in meta in core.admin_cms_page_updated"));

        $subject = new AdminCmsPageUpdated();
        $subject->injectServices($this->router, $this->imageService, ['cms_slug' => 'about']);

        // Act
        $subject->validate();
    }

    public function testCanCatchMissingCmsSlug(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'cms_slug' in meta in core.admin_cms_page_updated"));

        $subject = new AdminCmsPageUpdated();
        $subject->injectServices($this->router, $this->imageService, ['cms_id' => 5]);

        // Act
        $subject->validate();
    }
}
