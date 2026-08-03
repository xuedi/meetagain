<?php declare(strict_types=1);

namespace App\Publisher\WellKnown;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

class Registry
{
    /**
     * @var array<string, list<WellKnownProviderInterface>>|null
     */
    private ?array $bySuffix = null;

    /**
     * @param iterable<WellKnownProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(WellKnownProviderInterface::class)]
        private readonly iterable $providers = [],
    ) {}

    public function resolve(string $suffix, Request $request): ?WellKnownDocument
    {
        foreach ($this->getBySuffix()[$suffix] ?? [] as $provider) {
            $document = $provider->provide($request);
            if ($document !== null) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function getSuffixes(): array
    {
        return array_keys($this->getBySuffix());
    }

    /**
     * @return array<string, list<WellKnownProviderInterface>>
     */
    private function getBySuffix(): array
    {
        if ($this->bySuffix !== null) {
            return $this->bySuffix;
        }

        $providers = iterator_to_array($this->providers, false);
        usort($providers, static fn(WellKnownProviderInterface $a, WellKnownProviderInterface $b): int => $b->getPriority() <=> $a->getPriority());

        $map = [];
        foreach ($providers as $provider) {
            $map[$provider->getSuffix()][] = $provider;
        }

        return $this->bySuffix = $map;
    }
}
