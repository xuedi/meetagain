<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Repository\EmailBlocklistRepository;

/**
 * Wraps EmailBlocklistRepository with a per-request / per-CLI-invocation memo so a cron sweep
 * over thousands of recipients does not re-query the DB for the same address.
 *
 * Non-readonly because of the memo array.
 */
final class EmailBlocklistChecker implements BlocklistCheckerInterface
{
    /** @var array<string, bool> */
    private array $memo = [];

    public function __construct(
        private readonly EmailBlocklistRepository $repository,
    ) {}

    public function isBlocked(string $email): bool
    {
        $key = strtolower(trim($email));
        if ($key === '') {
            return false;
        }

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $this->repository->isBlocked($key);
    }
}
