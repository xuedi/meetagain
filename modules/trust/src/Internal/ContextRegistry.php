<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use Module\Trust\Contract\ContextDescriberInterface;
use Module\Trust\Contract\ContextDescriptor;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ContextRegistry
{
    /** @var array<string, ContextDescriptor|null> */
    private array $memo = [];

    /**
     * @param iterable<ContextDescriberInterface> $describers
     */
    public function __construct(
        #[AutowireIterator(ContextDescriberInterface::class)]
        private readonly iterable $describers,
    ) {}

    public function describe(string $context): ?ContextDescriptor
    {
        if (array_key_exists($context, $this->memo)) {
            return $this->memo[$context];
        }

        foreach ($this->describers as $describer) {
            $descriptor = $describer->describe($context);
            if ($descriptor !== null) {
                return $this->memo[$context] = $descriptor;
            }
        }

        return $this->memo[$context] = null;
    }

    public function exists(string $context): bool
    {
        return $this->describe($context) !== null;
    }

    /**
     * @return list<ContextDescriptor>
     */
    public function describeAll(): array
    {
        $all = [];
        foreach ($this->describers as $describer) {
            foreach ($describer->describeAll() as $descriptor) {
                $all[$descriptor->context] ??= $descriptor;
            }
        }

        return array_values($all);
    }
}
