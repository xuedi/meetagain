<?php declare(strict_types=1);

namespace Module\Trust\Contract;

/**
 * Moves vouches between instances, for data movers only. Unlike TrustInterface it reads every
 * member's edges, so nothing member-facing may call it.
 */
interface GrantTransferInterface
{
    /**
     * @param list<string> $contexts
     * @return list<PortableGrant> ordered by id
     */
    public function exportGrants(array $contexts): array;

    public function restoreGrant(PortableGrant $grant): void;
}
