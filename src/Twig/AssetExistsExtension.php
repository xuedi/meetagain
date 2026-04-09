<?php declare(strict_types=1);

namespace App\Twig;

use App\AssetMapper\OpaqueMediaPathResolver;
use App\ExtendedFilesystem;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetExistsExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $kernelProjectDir,
        private readonly ExtendedFilesystem $filesystem,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_exists', $this->assetExists(...)),
            new TwigFunction('get_app_bundle_url', OpaqueMediaPathResolver::appBundlePath(...)),
        ];
    }

    public function assetExists($path): bool
    {
        $assets = $this->kernelProjectDir . '/assets/';
        $toCheck = $this->filesystem->getRealPath($assets . $path);
        if ($toCheck === false) {
            return false;
        }

        return $this->filesystem->isFile($toCheck);
    }

    public function getName(): string
    {
        return 'asset_exists';
    }
}
