<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\ConsentService;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ConsentExtension extends AbstractExtension
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('show_cookie_consent', $this->consentService->getShowCookieConsent(...)),
            new TwigFunction('show_osm', $this->consentService->getShowOsm(...)),
        ];
    }
}
