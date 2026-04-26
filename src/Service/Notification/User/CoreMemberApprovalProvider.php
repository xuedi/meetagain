<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use App\Service\Member\UserService;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class CoreMemberApprovalProvider implements ReviewNotificationProviderInterface
{
    public function __construct(
        private UserRepository $userRepo,
        private UserService $userService,
        private Security $security,
    ) {}

    public function getIdentifier(): string
    {
        return 'core.member_approval';
    }

    public function getReviewItems(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return [];
        }

        $pendingUsers = $this->userRepo->findByStatus(UserStatus::EmailVerified);
        $items = [];

        foreach ($pendingUsers as $pendingUser) {
            $items[] = new ReviewNotificationItem(
                id: (string) $pendingUser->getId(),
                description: sprintf('User %s is waiting for approval', $pendingUser->getName()),
                canDeny: true,
                icon: 'user-check',
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Only admins can approve members.');
        }

        $pendingUser = $this->userRepo->find((int) $itemId);
        if ($pendingUser === null || $pendingUser->getStatus() !== UserStatus::EmailVerified) {
            throw new InvalidArgumentException('User not found or not pending approval.');
        }

        $this->userService->transitionStatus($user, $pendingUser, UserStatus::Active);
    }

    public function denyItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Only admins can deny members.');
        }

        $pendingUser = $this->userRepo->find((int) $itemId);
        if ($pendingUser === null || $pendingUser->getStatus() !== UserStatus::EmailVerified) {
            throw new InvalidArgumentException('User not found or not pending approval.');
        }

        $this->userService->transitionStatus($user, $pendingUser, UserStatus::Denied);
    }
}
