<?php declare(strict_types=1);

namespace Tests\Unit\AssetMapper;

use App\AssetMapper\OpaqueMediaPathResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolverInterface;

class OpaqueMediaPathResolverTest extends TestCase
{
    private PublicAssetsPathResolverInterface $inner;
    private OpaqueMediaPathResolver $resolver;

    protected function setUp(): void
    {
        $this->inner = $this->createStub(PublicAssetsPathResolverInterface::class);
        $this->resolver = new OpaqueMediaPathResolver($this->inner);
    }

    public function testResolvesImageToOpaqueMediaUrl(): void
    {
        // Arrange
        $logicalPath = 'images/logo.png';

        // Act
        $result = $this->resolver->resolvePublicPath($logicalPath);

        // Assert: starts with /media/ and preserves extension
        $this->assertStringStartsWith('/media/', $result);
        $this->assertStringEndsWith('.png', $result);
    }

    public function testHashIsDeterministic(): void
    {
        // Arrange
        $logicalPath = 'images/logo.png';

        // Act
        $first  = $this->resolver->resolvePublicPath($logicalPath);
        $second = $this->resolver->resolvePublicPath($logicalPath);

        // Assert: same input always produces the same URL
        $this->assertSame($first, $second);
    }

    public function testDifferentPathsProduceDifferentHashes(): void
    {
        // Arrange
        $pathA = 'images/logo.png';
        $pathB = 'images/banner.png';

        // Act
        $urlA = $this->resolver->resolvePublicPath($pathA);
        $urlB = $this->resolver->resolvePublicPath($pathB);

        // Assert
        $this->assertNotSame($urlA, $urlB);
    }

    public function testHashLengthIsCorrect(): void
    {
        // Arrange
        $logicalPath = 'images/logo.png';

        // Act
        $hash = OpaqueMediaPathResolver::hashLogicalPath($logicalPath);

        // Assert
        $this->assertSame(OpaqueMediaPathResolver::HASH_LENGTH, strlen($hash));
    }

    public function testJsFileDelegatesToInnerResolver(): void
    {
        // Arrange
        $logicalPath = 'js/app.js';
        $inner = $this->createMock(PublicAssetsPathResolverInterface::class);
        $inner->expects($this->once())
            ->method('resolvePublicPath')
            ->with($logicalPath)
            ->willReturn('/assets/js/app-abc123.js');
        $resolver = new OpaqueMediaPathResolver($inner);

        // Act & Assert
        $this->assertSame('/assets/js/app-abc123.js', $resolver->resolvePublicPath($logicalPath));
    }

    public function testMjsFileDelegatesToInnerResolver(): void
    {
        // Arrange
        $logicalPath = 'js/module.mjs';
        $inner = $this->createMock(PublicAssetsPathResolverInterface::class);
        $inner->expects($this->once())
            ->method('resolvePublicPath')
            ->with($logicalPath)
            ->willReturn('/assets/js/module-xyz.mjs');
        $resolver = new OpaqueMediaPathResolver($inner);

        // Act & Assert
        $this->assertSame('/assets/js/module-xyz.mjs', $resolver->resolvePublicPath($logicalPath));
    }

    public function testMapFileDelegatesToInnerResolver(): void
    {
        // Arrange
        $logicalPath = 'js/app.js.map';
        $inner = $this->createMock(PublicAssetsPathResolverInterface::class);
        $inner->expects($this->once())
            ->method('resolvePublicPath')
            ->with($logicalPath)
            ->willReturn('/assets/js/app.js.map');
        $resolver = new OpaqueMediaPathResolver($inner);

        // Act & Assert
        $this->assertSame('/assets/js/app.js.map', $resolver->resolvePublicPath($logicalPath));
    }

    public function testMissingExtensionFallsToBin(): void
    {
        // Arrange
        $logicalPath = 'data/somefile';

        // Act
        $result = $this->resolver->resolvePublicPath($logicalPath);

        // Assert
        $this->assertStringEndsWith('.bin', $result);
    }

    public function testScssExtensionIsNormalizedToCss(): void
    {
        // Arrange: SCSS logical paths are compiled to CSS by sass:build; URL must reflect the actual content type
        $logicalPath = 'styles/app.scss';

        // Act
        $result = $this->resolver->resolvePublicPath($logicalPath);

        // Assert: extension in URL is .css, not .scss
        $this->assertStringEndsWith('.css', $result);
        $this->assertStringStartsWith('/media/', $result);
    }
}
