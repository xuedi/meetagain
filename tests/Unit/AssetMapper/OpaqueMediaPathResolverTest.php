<?php declare(strict_types=1);

namespace Tests\Unit\AssetMapper;

use App\AssetMapper\OpaqueMediaPathResolver;
use PHPUnit\Framework\TestCase;

class OpaqueMediaPathResolverTest extends TestCase
{
    private OpaqueMediaPathResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OpaqueMediaPathResolver();
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

    public function testJsFileResolvesToOpaqueMediaUrl(): void
    {
        // Arrange
        $logicalPath = 'js/event-details.js';

        // Act
        $result = $this->resolver->resolvePublicPath($logicalPath);

        // Assert
        $this->assertStringStartsWith('/media/', $result);
        $this->assertStringEndsWith('.js', $result);
    }

    public function testMjsExtensionIsNormalizedToJs(): void
    {
        // Arrange: .mjs is served as JavaScript; URL normalizes to .js to match MediaCompileCommand output
        $logicalPath = 'js/module.mjs';

        // Act
        $result = $this->resolver->resolvePublicPath($logicalPath);

        // Assert
        $this->assertStringEndsWith('.js', $result);
        $this->assertStringStartsWith('/media/', $result);
    }

    public function testMapFileKeepsMapExtension(): void
    {
        // Arrange
        $logicalPath = 'js/app.js.map';

        // Act
        $result = $this->resolver->resolvePublicPath($logicalPath);

        // Assert
        $this->assertStringStartsWith('/media/', $result);
        $this->assertStringEndsWith('.map', $result);
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
