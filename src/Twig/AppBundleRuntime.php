<?php declare(strict_types=1);

namespace App\Twig;

use App\AssetMapper\AppBundle;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class AppBundleRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private AppBundle $appBundle,
    ) {}

    public function url(): string
    {
        return $this->appBundle->url();
    }
}
