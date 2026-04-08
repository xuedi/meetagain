<?php declare(strict_types=1);

namespace App\AssetMapper;

use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolverInterface;

final class OpaqueMediaPathResolver implements PublicAssetsPathResolverInterface
{
    public const int HASH_LENGTH = 16;
    private const string SECRET_SALT = 'meetagain-media-v1';

    public function __construct(private readonly PublicAssetsPathResolverInterface $inner) {}

    public function resolvePublicPath(string $logicalPath): string
    {
        $ext = pathinfo($logicalPath, PATHINFO_EXTENSION);
        if (in_array($ext, ['js', 'mjs', 'map'], true)) {
            return $this->inner->resolvePublicPath($logicalPath);
        }

        $ext = $ext ?: 'bin';
        return '/media/' . self::hashLogicalPath($logicalPath) . '.' . $ext;
    }

    public function getPublicFilesystemPath(): string
    {
        return $this->inner->getPublicFilesystemPath();
    }

    public static function hashLogicalPath(string $logicalPath): string
    {
        return substr(
            hash('sha256', self::SECRET_SALT . '|' . $logicalPath),
            0,
            self::HASH_LENGTH,
        );
    }
}
