<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Filter\CanonicalUrlProviderInterface;
use App\Service\Config\ConfigService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the canonical URL for the current request.
 *
 * Default behaviour (standalone install, no plugin registered):
 *   → ConfigService::getHost() + current request URI (self-referencing canonical)
 *
 * With a CanonicalUrlProviderInterface implementation registered (e.g. multisite plugin):
 *   → delegate to the provider; fall back to default if provider returns null
 */
readonly class CanonicalUrlService
{
    /**
     * @param iterable<CanonicalUrlProviderInterface> $providers
     */
    public function __construct(
        private ConfigService $configService,
        #[AutowireIterator(CanonicalUrlProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function getCanonicalUrl(Request $request): string
    {
        $defaultUrl = rtrim($this->configService->getHost(), '/') . $request->getPathInfo();

        foreach ($this->providers as $provider) {
            $override = $provider->getCanonicalUrl($defaultUrl, $request);
            if ($override !== null) {
                return $override;
            }
        }

        return $defaultUrl;
    }
}
