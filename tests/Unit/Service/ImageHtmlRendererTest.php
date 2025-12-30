<?php declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\ImageHtmlRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class ImageHtmlRendererTest extends TestCase
{
    private ImageRepository|MockObject $imageRepo;
    private Environment|MockObject $twig;
    private ImageHtmlRenderer $service;

    protected function setUp(): void
    {
        $this->imageRepo = $this->createMock(ImageRepository::class);
        $this->twig = $this->createMock(Environment::class);
        $this->service = new ImageHtmlRenderer($this->imageRepo, $this->twig);
    }

    public function testRenderThumbnail(): void
    {
        $image = new Image();
        $this->imageRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 123])
            ->willReturn($image);

        $this->twig->expects($this->once())
            ->method('render')
            ->with('_block/image.html.twig', [
                'image' => $image,
                'size' => '100x100',
            ])
            ->willReturn('<html>img</html>');

        $result = $this->service->renderThumbnail(123, '100x100');
        $this->assertEquals('<html>img</html>', $result);
    }

    public function testRenderThumbnailWithDefaultSize(): void
    {
        $image = null;
        $this->imageRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => 456])
            ->willReturn($image);

        $this->twig->expects($this->once())
            ->method('render')
            ->with('_block/image.html.twig', [
                'image' => $image,
                'size' => '50x50',
            ])
            ->willReturn('<html>no-img</html>');

        $result = $this->service->renderThumbnail(456);
        $this->assertEquals('<html>no-img</html>', $result);
    }
}
