<?php declare(strict_types=1);

namespace Tests\Unit\Service\Activity\Messages;

use App\Activity\MessageInterface;
use App\Activity\Messages\AdminCmsBlockUpdated;
use App\Service\Media\ImageHtmlRenderer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\IdentityTranslator;

class AdminCmsBlockUpdatedTest extends TestCase
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
        $meta = ['cms_id' => 5, 'cms_slug' => 'about', 'block_id' => 12, 'block_type' => 'Text'];

        $subject = new AdminCmsBlockUpdated();
        $subject->injectServices($this->router, $this->imageService, $this->translator, $meta);

        // Act & Assert
        static::assertInstanceOf(MessageInterface::class, $subject->validate());
        static::assertEquals(AdminCmsBlockUpdated::TYPE, $subject->getType());
        static::assertEquals('profile_social.activity_admin_cms_block_updated', $subject->render());
        static::assertEquals('profile_social.activity_admin_cms_block_updated', $subject->render(true));
    }

    public function testCanCatchMissingBlockId(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'block_id' in meta in core.admin_cms_block_updated"));

        $subject = new AdminCmsBlockUpdated();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['cms_id' => 5, 'block_type' => 'Text']);

        // Act
        $subject->validate();
    }

    public function testCanCatchMissingBlockType(): void
    {
        // Arrange
        $this->expectExceptionObject(new InvalidArgumentException("Missing 'block_type' in meta in core.admin_cms_block_updated"));

        $subject = new AdminCmsBlockUpdated();
        $subject->injectServices($this->router, $this->imageService, $this->translator, ['cms_id' => 5, 'block_id' => 12]);

        // Act
        $subject->validate();
    }
}
