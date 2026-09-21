<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use App\Entity\User;
use App\Filter\Member\PendingApprovalFilterService;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

readonly class UserPendingApprovalNotificationProvider implements AdminNotificationProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private PendingApprovalFilterService $pendingApproval,
    ) {}

    public function getSection(): TranslatableInterface
    {
        return new TranslatableMessage('notifications.section_pending_approval');
    }

    public function getPendingItems(User $recipient): array
    {
        $items = [];
        foreach ($this->pendingApproval->pendingFor($recipient) as $user) {
            $items[] = new AdminNotificationItem(label: sprintf('%s (%s)', $user->getName(), $user->getEmail()), route: 'app_admin_member');
        }

        return $items;
    }

    public function getLatestPendingAt(): ?DateTimeImmutable
    {
        return $this->userRepository->getLatestPendingCreatedAt();
    }
}
