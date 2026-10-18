<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Repository\SuspiciousUrlRepository;
use Symfony\Contracts\Service\ResetInterface;

final class SuspiciousUrlMatcher implements ResetInterface
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

    public function reset(): void
    {
        $this->urlSet = null;
    }
}
