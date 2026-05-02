<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Repository\EmailBlocklistRepository;

/**
 * Wraps EmailBlocklistRepository with a per-request / per-CLI-invocation cache. The first call
 * loads the entire blocklist into memory in a single query; subsequent lookups are O(1) hash
 * checks. Blocklists are hand-curated bounce/spam addresses, so the full set fits in RAM trivially.
 *
 * Non-readonly because of the lazy loaded set.
 */
final class EmailBlocklistChecker implements BlocklistCheckerInterface
{
    /** @var array<string, true>|null */
    private ?array $blockedSet = null;

    public function __construct(
        private readonly EmailBlocklistRepository $repository,
    ) {}

    public function isBlocked(string $email): bool
    {
        $key = strtolower(trim($email));
        if ($key === '') {
            return false;
        }

        if ($this->blockedSet === null) {
            $this->blockedSet = [];
            foreach ($this->repository->findAllOrdered() as $entry) {
                $this->blockedSet[strtolower(trim((string) $entry->getEmail()))] = true;
            }
        }

        return isset($this->blockedSet[$key]);
    }
}
