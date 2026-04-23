<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminCmsPageUpdated;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class AdminCmsPageUpdatedTest extends TestCase
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
        // Arrange
        $meta = ['cms_id' => 5, 'cms_slug' => 'about'];

        $subject = new AdminCmsPageUpdated();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminCmsPageUpdated::TYPE, $subject->getType());
        static::assertEquals('profile_social.activity_admin_cms_page_updated', $subject->render());
        static::assertEquals('profile_social.activity_admin_cms_page_updated', $subject->render(true));
    }

    public function testCanCatchMissingCmsId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'cms_id' in meta in core.admin_cms_page_updated"));

        $subject = new AdminCmsPageUpdated();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['cms_slug' => 'about']);

        // Act
        $subject->validate();
    }

    public function testCanCatchMissingCmsSlug(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'cms_slug' in meta in core.admin_cms_page_updated"));

        $subject = new AdminCmsPageUpdated();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['cms_id' => 5]);

        // Act
        $subject->validate();
    }
}
