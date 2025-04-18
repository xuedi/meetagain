<?php declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetExistsExtension extends AbstractExtension
{
    public function __construct(private string $kernelProjectDir)
    {
    }

    public function getFunctions(): array
    {
        return [
           new TwigFunction('asset_exists', [$this, 'assetExists']),
        ];
    }

    public function assetExists($path): bool
    {
        $assets = $this->kernelProjectDir . '/assets/';
        $toCheck = realpath($assets . $path);

        return is_file($toCheck);
    }

    public function getName(): string
    {
        return 'asset_exists';
    }
}
