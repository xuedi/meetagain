<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Publisher\CanonicalUrl\CanonicalUrlProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

readonly class CanonicalUrlService
{
    /**
     * @param iterable<CanonicalUrlProviderInterface> $providers
     */
    public function __construct(
        private UrlOwnerService $urlOwnerService,
        #[AutowireIterator(CanonicalUrlProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function getCanonicalUrl(Request $request): string
    {
        $defaultUrl = $this->urlOwnerService->getOwnerHostForRequest($request) . $request->getPathInfo();

        foreach ($this->providers as $provider) {
            $override = $provider->getCanonicalUrl($defaultUrl, $request);
            if ($override !== null) {
                return $override;
            }
        }

        return $defaultUrl;
    }
}
