<?php declare(strict_types=1);

namespace App\Circulation;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ContextResolver
{
    /**
     * @param iterable<ContextProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(ContextProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function resolve(string $itemType): string
    {
        $ordered = iterator_to_array($this->providers, false);
        usort($ordered, static fn(ContextProviderInterface $a, ContextProviderInterface $b): int => $b->getPriority() <=> $a->getPriority());

        foreach ($ordered as $provider) {
            $context = $provider->getContext($itemType);
            if ($context !== null) {
                return $context;
            }
        }

        throw new RuntimeException(sprintf('No circulation context provider claimed the item type "%s".', $itemType));
    }
}
