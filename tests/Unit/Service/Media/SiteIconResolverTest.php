<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Publisher\SiteIcon\SiteIconProviderInterface;
use App\Service\Media\ImageService;
use App\Service\Media\SiteIconResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SiteIconResolverTest extends TestCase
{
    public function testWithoutProvidersNothingResolvesAndNoUrlIsOffered(): void
    {
        // Arrange
        $resolver = $this->resolver([], $this->createStub(ImageService::class));

        // Act & Assert
        static::assertNull($resolver->resolveImage());
        static::assertNull($resolver->url());
        static::assertNull($resolver->renderPng());
    }

    public function testTheFirstNonNullProviderWins(): void
    {
        // Arrange
        $first = new Image()->setHash('first');
        $resolver = $this->resolver([
            $this->provider(null),
            $this->provider($first),
            $this->provider(new Image()->setHash('second')),
        ], $this->createStub(ImageService::class));

        // Act & Assert
        static::assertSame($first, $resolver->resolveImage());
    }

    public function testTheUrlPointsAtTheEndpointAndChangesWithTheImage(): void
    {
        // Arrange
        $imageService = $this->createStub(ImageService::class);

        // Act
        $first = $this->resolver([$this->provider($this->imageWithId(7))], $imageService)->url();
        $replaced = $this->resolver([$this->provider($this->imageWithId(8))], $imageService)->url();

        // Assert
        static::assertSame(SiteIconResolver::ENDPOINT_PATH . '?v7', $first);
        static::assertNotSame($first, $replaced);
    }

    public function testTheIconIsRasterisedFromItsOriginalAtTheIconSize(): void
    {
        // Arrange
        $image = new Image()->setHash('iconHash');
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->once())->method('readOriginal')->with($image)->willReturn('original-bytes');
        $imageService->expects($this->once())->method('renderPng')->with('original-bytes', SiteIconResolver::SIZE)->willReturn('png-bytes');

        // Act
        $rendered = $this->resolver([$this->provider($image)], $imageService)->renderPng();

        // Assert
        static::assertSame('png-bytes', $rendered['content'] ?? null);
        static::assertNotSame('', $rendered['etag'] ?? '');
    }

    public function testAMissingOriginalReportsNothing(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('readOriginal')->willReturn(null);
        $imageService->expects($this->never())->method('renderPng');

        // Act & Assert
        static::assertNull($this->resolver([$this->provider(new Image()->setHash('iconHash'))], $imageService)->renderPng());
    }

    public function testTheSameIconIsRasterisedOnceAcrossRepeatedRequests(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('readOriginal')->willReturn('original-bytes');
        $imageService->expects($this->once())->method('renderPng')->willReturn('png-bytes');
        $resolver = $this->resolver([$this->provider(new Image()->setHash('iconHash'))], $imageService);

        // Act
        $first = $resolver->renderPng();
        $second = $resolver->renderPng();

        // Assert
        static::assertSame($first, $second);
    }

    private function imageWithId(int $id): Image
    {
        $image = $this->createStub(Image::class);
        $image->method('getId')->willReturn($id);

        return $image;
    }

    private function provider(?Image $image): SiteIconProviderInterface
    {
        $provider = $this->createStub(SiteIconProviderInterface::class);
        $provider->method('resolveSiteIcon')->willReturn($image);

        return $provider;
    }

    /**
     * @param list<SiteIconProviderInterface> $providers
     */
    private function resolver(array $providers, ImageService $imageService): SiteIconResolver
    {
        return new SiteIconResolver($providers, $imageService, new ArrayAdapter());
    }
}
