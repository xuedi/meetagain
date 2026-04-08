<?php declare(strict_types=1);

namespace Tests\Unit\AssetMapper;

use App\AssetMapper\MediaMap;
use App\AssetMapper\OpaqueMediaPathResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

class MediaMapTest extends TestCase
{
    public function testBuildReturnsHashToLogicalPathMap(): void
    {
        // Arrange
        $assetA = new MappedAsset('images/logo.png', sourcePath: '/src/images/logo.png');
        $assetB = new MappedAsset('images/banner.webp', sourcePath: '/src/images/banner.webp');

        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper->method('allAssets')->willReturn([$assetA, $assetB]);

        $mediaMap = new MediaMap($assetMapper);

        // Act
        $map = $mediaMap->build();

        // Assert: map is keyed by hash, value is logical path
        $expectedHashA = OpaqueMediaPathResolver::hashLogicalPath('images/logo.png');
        $expectedHashB = OpaqueMediaPathResolver::hashLogicalPath('images/banner.webp');

        $this->assertArrayHasKey($expectedHashA, $map);
        $this->assertArrayHasKey($expectedHashB, $map);
        $this->assertSame('images/logo.png', $map[$expectedHashA]);
        $this->assertSame('images/banner.webp', $map[$expectedHashB]);
    }

    public function testBuildReturnsEmptyMapWhenNoAssets(): void
    {
        // Arrange
        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper->method('allAssets')->willReturn([]);

        $mediaMap = new MediaMap($assetMapper);

        // Act & Assert
        $this->assertSame([], $mediaMap->build());
    }
}
