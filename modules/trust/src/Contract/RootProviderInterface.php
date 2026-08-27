<?php declare(strict_types=1);

namespace Module\Trust\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Hands a member the standing they hold by authority rather than by earning it.
 */
#[AutoconfigureTag]
interface RootProviderInterface
{
    /** Root points for this member, or null to pass to the next provider. */
    public function getRootPoints(string $context, int $userId): ?int;

    /**
     * Every member this provider hands root points to, so the graph can be built
     * without asking about members it has never heard of.
     *
     * @return iterable<int>
     */
    public function getRootUserIds(string $context): iterable;
}
