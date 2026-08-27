<?php declare(strict_types=1);

namespace Module\Trust\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Feeds a consumer's own immutable record into the graph. Every source contributes;
 * none of them wins over another. A source both emits actions and declares what they mean.
 */
#[AutoconfigureTag]
interface ActionSourceInterface
{
    /**
     * The action kinds this source contributes to the context, empty for a context it does not
     * serve. An action replayed without a descriptor scores nothing.
     *
     * @return iterable<ActionDescriptor>
     */
    public function describeActions(string $context): iterable;

    /**
     * Must be replay-stable: the same context yields the same actions every time.
     *
     * @return iterable<TrustAction>
     */
    public function replay(string $context): iterable;

    /**
     * Watermark that changes whenever replay() would answer differently. Null means
     * this source contributes nothing to the context.
     */
    public function getRevision(string $context): ?string;
}
