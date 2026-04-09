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

        $stablePath = preg_replace('/-[A-Za-z0-9_-]{7}(\.\w+)$/', '$1', $logicalPath);

        $ext = self::normalizeExtension($ext);
        return '/media/' . self::hashLogicalPath($stablePath) . '.' . $ext;
    }

    private static function normalizeExtension(string $ext): string
    {
        return match ($ext) {
            'scss', 'sass' => 'css',
            'mjs'          => 'js',
            default        => $ext,
        };
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

    /** Public path of the compiled global JS bundle under /media/. */
    public static function appBundlePath(): string
    {
        return '/media/' . self::hashLogicalPath('js/app-bundle') . '.js';
    }
}
