<?php declare(strict_types=1);

namespace Module\Trust\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Claims an opaque context string as one this consumer owns. A context no describer
 * claims does not exist: it cannot be scored, viewed or administered.
 */
#[AutoconfigureTag]
interface ContextDescriberInterface
{
    /** Descriptor for a context this consumer owns, or null to pass to the next describer. */
    public function describe(string $context): ?ContextDescriptor;

    /**
     * Every context this consumer currently owns, for the operator listing.
     *
     * @return iterable<ContextDescriptor>
     */
    public function describeAll(): iterable;
}
