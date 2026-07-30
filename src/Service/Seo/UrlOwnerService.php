<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Publisher\UrlOwner\UrlOwnerProviderInterface;
use App\Service\Config\ConfigService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class UrlOwnerService
{
    /**
     * @param iterable<UrlOwnerProviderInterface> $providers
     */
    public function __construct(
        private ConfigService $configService,
        #[AutowireIterator(UrlOwnerProviderInterface::class)]
        private iterable $providers,
    ) {}

    /**
     * @param array<string, mixed> $parameters
     */
    public function getOwnerHost(string $route, array $parameters = []): string
    {
        return $this->resolveClaim($route, $parameters) ?? rtrim($this->configService->getHost(), '/');
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function ownsUrl(string $route, array $parameters, string $url): bool
    {
        $claimedHost = $this->resolveClaim($route, $parameters);
        $isUnclaimed = $claimedHost === null;

        return $isUnclaimed || parse_url($claimedHost, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function resolveClaim(string $route, array $parameters): ?string
    {
        foreach ($this->providers as $provider) {
            $ownerHost = $provider->getOwnerHost($route, $parameters);
            if ($ownerHost !== null && $ownerHost !== '') {
                return rtrim($ownerHost, '/');
            }
        }

        return null;
    }
}
