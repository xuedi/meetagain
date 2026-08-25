<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\ActionSourceInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

final class ActionRegistry implements ResetInterface
{
    /** @var array<string, array<string, ActionDescriptor>> */
    private array $memo = [];

    /**
     * @param iterable<ActionSourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator(ActionSourceInterface::class)]
        private readonly iterable $sources,
    ) {}

    /**
     * @return array<string, ActionDescriptor>
     */
    public function forContext(string $context): array
    {
        if (array_key_exists($context, $this->memo)) {
            return $this->memo[$context];
        }

        $descriptors = [];
        foreach ($this->sources as $source) {
            foreach ($source->describeActions($context) as $descriptor) {
                $descriptors[$descriptor->key] ??= $descriptor;
            }
        }

        return $this->memo[$context] = $descriptors;
    }

    #[Override]
    public function reset(): void
    {
        $this->memo = [];
    }
}
