<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Media\AltLocaleRequirementResolver;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AltLocaleExtension extends AbstractExtension
{
    public function __construct(private readonly AltLocaleRequirementResolver $resolver) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('required_alt_locales', $this->resolver->getRequiredAltLocales(...)),
        ];
    }
}
