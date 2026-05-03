<?php declare(strict_types=1);

namespace App\Service\Api;

use App\Service\Config\ActivePluginListInterface;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Short-TTL pull-through cache for read-only API responses.
 *
 * The helper is GET-only: any non-GET method bypasses the cache entirely.
 * Bearer-authenticated requests also bypass; cached payloads are reserved
 * for anonymous traffic so a logged-in user's filtered view never leaks.
 *
 * Cache keys include the request host and the active-plugin-set fingerprint
 * so per-domain and per-group activation produces naturally segregated
 * payloads. No explicit invalidation; entries age out via TTL.
 */
readonly class ApiCache
{
    public function __construct(
        private CacheInterface $appCache,
        private ActivePluginListInterface $pluginService,
    ) {}

    /**
     * @param array<string, mixed> $varyParams
     * @param callable(): mixed $compute
     */
    public function getJson(
        Request $request,
        string $endpointKey,
        int $ttlSeconds,
        array $varyParams,
        callable $compute,
    ): mixed {
        if ($request->getMethod() !== 'GET') {
            return $compute();
        }
        if ($request->headers->has('Authorization')) {
            return $compute();
        }

        $cacheKey = $this->buildCacheKey($request, $endpointKey, $varyParams);

        return $this->appCache->get($cacheKey, function (ItemInterface $item) use ($ttlSeconds, $compute): mixed {
            $item->expiresAfter($ttlSeconds);

            return $compute();
        });
    }

    /**
     * @param array<string, mixed> $varyParams
     */
    private function buildCacheKey(Request $request, string $endpointKey, array $varyParams): string
    {
        ksort($varyParams);
        try {
            $varyJson = json_encode($varyParams, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $varyJson = serialize($varyParams);
        }
        $vary = sha1($varyJson);

        $host = $request->getSchemeAndHttpHost();
        $hostHash = sha1($host);

        $activePlugins = $this->pluginService->getActiveList();
        sort($activePlugins);
        $pluginFingerprint = sha1(implode(',', $activePlugins));

        return sprintf('api.%s.%s.%s.%s', $endpointKey, $hostHash, $pluginFingerprint, $vary);
    }
}
