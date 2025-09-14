<?php declare(strict_types=1);

namespace App\Twig;

use App\ExtendedFilesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetExistsExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $kernelProjectDir,
        private readonly ExtendedFilesystem $filesystem,
    ) {}

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset_exists', $this->assetExists(...)),
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
