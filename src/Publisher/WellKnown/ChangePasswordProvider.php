<?php declare(strict_types=1);

namespace App\Publisher\WellKnown;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ChangePasswordProvider implements WellKnownProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function getSuffix(): string
    {
        return 'change-password';
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    public function provide(Request $request): ?WellKnownDocument
    {
        return WellKnownDocument::redirect($this->urlGenerator->generate(
            'app_profile',
            ['_locale' => $request->getLocale()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }
}
