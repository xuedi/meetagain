<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Filter\Member\PendingApprovalFilterService;
use App\Repository\UserRepository;
use App\Service\Member\UserService;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CoreMemberApprovalProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private UserRepository $userRepo,
        private UserService $userService,
        private PendingApprovalFilterService $pendingApproval,
        private TranslatorInterface $translator,
    ) {}

    public function getIdentifier(): string
    {
        return 'core.member_approval';
    }

    public function getReviewItems(User $user): array
    {
        $items = [];
        foreach ($this->pendingApproval->pendingFor($user) as $pendingUser) {
            $items[] = new ReviewNotificationItem(
                id: (string) $pendingUser->getId(),
                description: $this->translator->trans('profile_review.member_approval_description', [
                    '%name%' => $pendingUser->getName(),
                ]),
                canDeny: true,
                icon: 'user-check',
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        $this->userService->transitionStatus($user, $this->reviewablePendingUser($user, $itemId), UserStatus::Active);
    }

    public function denyItem(User $user, string $itemId): void
    {
        $this->userService->transitionStatus($user, $this->reviewablePendingUser($user, $itemId), UserStatus::Denied);
    }

    private function reviewablePendingUser(User $reviewer, string $itemId): User
    {
        $pendingUser = $this->userRepo->find((int) $itemId);
        if ($pendingUser === null || $pendingUser->getStatus() !== UserStatus::EmailVerified) {
            throw new InvalidArgumentException('User not found or not pending approval.');
        }

        if (!$this->pendingApproval->mayReview($reviewer, $pendingUser)) {
            throw new AccessDeniedException('You may not decide on this member.');
        }

        return $pendingUser;
    }
}
