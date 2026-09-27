<?php declare(strict_types=1);

namespace Module\Email\Contract;

interface BlocklistInterface
{
    public function isBlocked(string $email): bool;

    /**
     * The reason recorded for a blocked address, or null when the address is not blocked.
     */
    public function reasonFor(string $email): ?string;

    /**
     * Blocks the address. Adding an address that is already blocked keeps the first entry and its reason.
     */
    public function add(string $email, string $reason): void;
}
