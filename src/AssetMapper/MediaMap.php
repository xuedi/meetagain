<?php declare(strict_types=1);

namespace App\AssetMapper;

use Symfony\Component\AssetMapper\AssetMapperInterface;

final readonly class MediaMap
{
    public function __construct(private AssetMapperInterface $assetMapper) {}

    /** @return array<string, string> hash => logicalPath */
    public function build(): array
    {
        $map = [];
        foreach ($this->assetMapper->allAssets() as $asset) {
            $hash = OpaqueMediaPathResolver::hashLogicalPath($asset->logicalPath);
            $map[$hash] = $asset->logicalPath;
        }
        return $map;
    }
}
