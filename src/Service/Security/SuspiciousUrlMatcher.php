<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Repository\SuspiciousUrlRepository;

final class SuspiciousUrlMatcher
{
    /** @var array<string, true>|null */
    private ?array $urlSet = null;

    public function __construct(
        private readonly SuspiciousUrlRepository $repository,
    ) {}

    public function matches(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if ($this->urlSet === null) {
            $this->urlSet = [];
            foreach ($this->repository->findAllUrls() as $entry) {
                $this->urlSet[$entry] = true;
            }
        }

        return isset($this->urlSet[$url]);
    }
}
