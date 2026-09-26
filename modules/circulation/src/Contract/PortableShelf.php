<?php declare(strict_types=1);

namespace Module\Circulation\Contract;

final readonly class PortableShelf
{
    /**
     * @param list<PortableCopy> $copies
     * @param list<PortableRequest> $requests
     * @param list<PortableHandover> $handovers
     * @param list<PortableLedgerEntry> $ledger oldest first
     */
    public function __construct(
        public array $copies = [],
        public array $requests = [],
        public array $handovers = [],
        public array $ledger = [],
    ) {}
}
