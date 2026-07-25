<?php declare(strict_types=1);

namespace App\Localization;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class LocalizedContentSourceRegistry
{
    /**
     * @param iterable<LocalizedContentSourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator(LocalizedContentSourceInterface::class)]
        private iterable $sources,
    ) {}

    /**
     * @return list<LocalizedContentSourceInterface>
     */
    public function all(): array
    {
        $sources = [];
        foreach ($this->sources as $source) {
            $sources[] = $source;
        }

        return $sources;
    }
}
