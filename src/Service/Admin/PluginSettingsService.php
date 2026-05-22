<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\Publisher\PluginSettings\PluginSettingsProviderInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Traversable;

final readonly class PluginSettingsService
{
    /** @var array<string, PluginSettingsProviderInterface> */
    private array $providers;

    /**
     * @param iterable<PluginSettingsProviderInterface> $providers
     */
    public function __construct(#[AutowireIterator(PluginSettingsProviderInterface::class)] iterable $providers)
    {
        $materialised = $providers instanceof Traversable ? iterator_to_array($providers, false) : array_values($providers);

        usort($materialised, static fn(PluginSettingsProviderInterface $a, PluginSettingsProviderInterface $b): int => $b->getPriority() <=> $a->getPriority());

        $keyed = [];
        foreach ($materialised as $provider) {
            $key = $provider->getKey();
            if (isset($keyed[$key])) {
                throw new LogicException(sprintf('Duplicate plugin settings provider key "%s": %s and %s.', $key, $keyed[$key]::class, $provider::class));
            }
            $keyed[$key] = $provider;
        }

        $this->providers = $keyed;
    }

    /** @return array<string, PluginSettingsProviderInterface> */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getProvider(string $key): ?PluginSettingsProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    public function hasAny(): bool
    {
        return $this->providers !== [];
    }
}
