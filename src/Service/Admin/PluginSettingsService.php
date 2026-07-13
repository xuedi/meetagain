<?php declare(strict_types=1);

namespace App\Service\Admin;

use App\Publisher\PluginSettings\PluginSettingsDescriptorInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Traversable;

/**
 * Registry of plugin settings descriptors, keyed by descriptor key and priority-sorted.
 */
final readonly class PluginSettingsService
{
    /** @var array<string, PluginSettingsDescriptorInterface> */
    private array $descriptors;

    /**
     * @param iterable<PluginSettingsDescriptorInterface> $descriptors
     */
    public function __construct(#[AutowireIterator(PluginSettingsDescriptorInterface::class)] iterable $descriptors)
    {
        $materialised = $descriptors instanceof Traversable ? iterator_to_array($descriptors, false) : array_values($descriptors);

        usort($materialised, static fn(PluginSettingsDescriptorInterface $a, PluginSettingsDescriptorInterface $b): int => $b->getPriority() <=> $a->getPriority());

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

    /** @return array<string, PluginSettingsDescriptorInterface> */
    public function getProviders(): array
    {
        return $this->descriptors;
    }

    public function getProvider(string $key): ?PluginSettingsDescriptorInterface
    {
        return $this->descriptors[$key] ?? null;
    }

    public function hasAny(): bool
    {
        return $this->descriptors !== [];
    }
}
