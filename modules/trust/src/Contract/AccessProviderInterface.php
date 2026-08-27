<?php declare(strict_types=1);

namespace Module\Trust\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Answers who may see and who may administer a context. Trust owns no roles of its own.
 */
#[AutoconfigureTag]
interface AccessProviderInterface
{
    /** Whether this member may see the context's surfaces, or null to pass on. */
    public function canView(string $context, int $userId): ?bool;

    /** Whether this member may change the context's configuration, or null to pass on. */
    public function canAdminister(string $context, int $userId): ?bool;
}
