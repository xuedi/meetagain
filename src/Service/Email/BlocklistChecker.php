<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Repository\EmailBlocklistRepository;

final class BlocklistChecker implements BlocklistCheckerInterface
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
