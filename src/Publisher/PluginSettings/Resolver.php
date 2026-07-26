<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use App\Service\Admin\PluginSettingsService;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class Resolver
{
    /** @var array<string, object> */
    private array $memo = [];

    /**
     * @param iterable<StoreInterface>         $stores
     * @param iterable<ScopeProviderInterface> $scopeProviders
     */
    public function __construct(
        private readonly PluginSettingsService $descriptors,
        #[AutowireIterator(StoreInterface::class)]
        private readonly iterable $stores,
        #[AutowireIterator(ScopeProviderInterface::class)]
        private readonly iterable $scopeProviders,
    ) {}

    public function resolve(string $key): object
    {
        $descriptor = $this->descriptors->getProvider($key);
        if ($descriptor === null) {
            throw new InvalidArgumentException(sprintf('Unknown plugin settings key "%s".', $key));
        }

        $scopeId = $this->resolveScopeId();
        $memoKey = $key . "\0" . ($scopeId ?? '');
        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        if ($scopeId !== null) {
            $override = $this->resolveStore($key, $scopeId)?->load($key, $scopeId);
            if ($override !== null) {
                return $this->memo[$memoKey] = $override;
            }
        }

        $global = $this->resolveStore($key, null)?->load($key, null);
        if ($global !== null) {
            return $this->memo[$memoKey] = $global;
        }

        return $this->memo[$memoKey] = $descriptor->createDefault();
    }

    public function resolveScopeId(): ?string
    {
        foreach ($this->scopeProviders as $provider) {
            $scopeId = $provider->getScopeId();
            if ($scopeId !== null) {
                return $scopeId;
            }
        }

        return null;
    }

    public function resolveStore(string $key, ?string $scopeId): ?StoreInterface
    {
        $best = null;
        foreach ($this->stores as $store) {
            if (!$store->supports($key, $scopeId)) {
                continue;
            }
            if ($best === null || $store->getPriority() > $best->getPriority()) {
                $best = $store;
            }
        }

        return $best;
    }
}
