<?php declare(strict_types=1);

namespace App\Twig;

use App\Publisher\OgImage\ResolvedOgImage;
use App\Service\Media\OgImageResolver;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class OgImageRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private OgImageResolver $resolver,
    ) {}

    public function resolve(): ?ResolvedOgImage
    {
        return $this->resolver->resolve();
    }
}
