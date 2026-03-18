<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use App\Entity\UserStatus;
use App\Repository\UserRepository;
use DateTimeImmutable;

readonly class UserPendingApprovalNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function getSection(): string
    {
        return 'Users Pending Approval';
    }

    public function getPendingItems(): array
    {
        $users = $this->userRepository->findByStatus(UserStatus::EmailVerified);
        $items = [];

        foreach ($users as $user) {
            $items[] = new AdminNotificationItem(
                label: sprintf('%s (%s)', $user->getName(), $user->getEmail()),
                route: 'app_admin_member',
            );
        }

        return $items;
    }

    public function getLatestPendingAt(): ?DateTimeImmutable
    {
        return $this->userRepository->getLatestPendingCreatedAt();
    }
}
