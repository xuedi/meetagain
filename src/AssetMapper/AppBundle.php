<?php declare(strict_types=1);

namespace App\AssetMapper;

use RuntimeException;
use Symfony\Component\AssetMapper\AssetMapperInterface;

final class AppBundle
{
    /**
     * Order is significant: ma-fetch must be first (others depend on maFetch).
     * Matches the per-file load order used in dev (templates/base.html.twig).
     */
    public const array SOURCES = [
        'js/ma-fetch.js',
        'js/notifications.js',
        'js/navbar.js',
        'js/toggles.js',
        'js/cookie-consent.js',
        'js/modal.js',
        'js/block-user.js',
        'js/post-link.js',
        'js/item-translation-toggle.js',
    ];

    private ?string $hash = null;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
    ) {}

    public function url(): string
    {
        return '/media/' . $this->filename();
    }

    public function filename(): string
    {
        return $this->hash() . '.js';
    }

    public function hash(): string
    {
        if ($this->hash !== null) {
            return $this->hash;
        }

        $content = '';
        foreach (self::SOURCES as $logicalPath) {
            $asset = $this->assetMapper->getAsset($logicalPath);
            if ($asset === null) {
                throw new RuntimeException("Global JS bundle: asset '{$logicalPath}' not found in AssetMapper.");
            }
            $content .= $asset->content ?? file_get_contents($asset->sourcePath);
        }

        return $this->hash = substr(hash('sha256', $content), 0, OpaqueMediaPathResolver::HASH_LENGTH);
    }
}
