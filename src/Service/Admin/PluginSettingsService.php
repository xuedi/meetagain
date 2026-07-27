<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\Publisher\PluginSettings\DescriptorInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Traversable;

final readonly class PluginSettingsService
{
    /** @var array<string, DescriptorInterface> */
    private array $descriptors;

    /**
     * @param iterable<DescriptorInterface> $descriptors
     */
    public function __construct(#[AutowireIterator(DescriptorInterface::class)] iterable $descriptors)
    {
        $materialised = $descriptors instanceof Traversable ? iterator_to_array($descriptors, false) : array_values($descriptors);

        usort(
            $materialised,
            static fn(DescriptorInterface $a, DescriptorInterface $b): int => $b->getPriority() <=> $a->getPriority(),
        );

        $keyed = [];
        foreach ($materialised as $descriptor) {
            $key = $descriptor->getKey();
            if (isset($keyed[$key])) {
                throw new LogicException(sprintf('Duplicate plugin settings descriptor key "%s": %s and %s.', $key, $keyed[$key]::class, $descriptor::class));
            }
            $keyed[$key] = $descriptor;
        }

        $this->descriptors = $keyed;
    }

    /** @return array<string, DescriptorInterface> */
    public function getProviders(): array
    {
        return $this->descriptors;
    }

    public function getProvider(string $key): ?DescriptorInterface
    {
        return $this->descriptors[$key] ?? null;
    }

    /** @return array<string, DescriptorInterface> */
    public function getByPlugin(string $pluginKey): array
    {
        return array_filter(
            $this->descriptors,
            static fn(DescriptorInterface $descriptor): bool => $descriptor->getPluginKey() === $pluginKey,
        );
    }

    /** @return list<string> */
    public function getConfigurablePluginKeys(): array
    {
        $keys = [];
        foreach ($this->descriptors as $descriptor) {
            $keys[$descriptor->getPluginKey()] = true;
        }

        return array_keys($keys);
    }

    /**
     * @return array<string, list<DescriptorInterface>>
     */
    public function getScopableByPlugin(): array
    {
        $grouped = [];
        foreach ($this->descriptors as $descriptor) {
            if (!$descriptor->isScopable()) {
                continue;
            }
            $grouped[$descriptor->getPluginKey()][] = $descriptor;
        }

        return $grouped;
    }

    public function hasAny(): bool
    {
        return $this->descriptors !== [];
    }
}
