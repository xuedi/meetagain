<?php declare(strict_types=1);

namespace App\Twig;

use App\AssetMapper\AppBundle;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppBundleExtension extends AbstractExtension
{
    public function __construct(
        private readonly AppBundle $appBundle,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_app_bundle_url', $this->appBundle->url(...)),
        ];
    }
}
