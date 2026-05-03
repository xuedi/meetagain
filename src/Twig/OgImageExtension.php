<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Media\OgImageResolver;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OgImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly OgImageResolver $resolver,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_og_image', $this->resolver->resolve(...)),
        ];
    }
}
