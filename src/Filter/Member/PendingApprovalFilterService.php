<?php declare(strict_types=1);

namespace App\Filter\Member;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class PendingApprovalFilterService
{
    /**
     * @param iterable<PendingApprovalFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(PendingApprovalFilterInterface::class)]
        private iterable $filters,
        private UserRepository $userRepository,
    ) {}

    /**
     * @return list<User>
     */
    public function pendingFor(User $reviewer): array
    {
        $pending = array_values($this->userRepository->findByStatus(UserStatus::EmailVerified));
        $narrowed = false;

        foreach ($this->filters as $filter) {
            $result = $filter->filterForReviewer($pending, $reviewer);
            if ($result === null) {
                continue;
            }

            $narrowed = true;
            $pending = array_values($result);
            if ($pending === []) {
                return [];
            }
        }

        if ($narrowed || $reviewer->getRole() === UserRole::Admin) {
            return $pending;
        }

        return [];
    }

    public function mayReview(User $reviewer, User $pendingUser): bool
    {
        return array_any($this->pendingFor($reviewer), static fn(User $user): bool => $user->getId() === $pendingUser->getId());
    }
}
