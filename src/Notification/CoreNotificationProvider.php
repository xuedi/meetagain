<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class CoreNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private ImageRepository $imageRepo,
        private EmailQueueRepository $emailRepo,
        private UserRepository $userRepo,
        private Security $security,
    ) {}

    public function getPriority(): int
    {
        return 0;
    }

    public function getNotifications(User $user): array
    {
        if (!$this->security->isGranted('ROLE_FOUNDER')) {
            return [];
        }

        $items = [];

        $reportedCount = $this->imageRepo->getReportedCount();
        if ($reportedCount > 0) {
            $items[] = new NotificationItem(
                label: $reportedCount . ' Reported Image' . ($reportedCount > 1 ? 's' : ''),
                icon: 'fa-flag',
            );
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $staleEmails = $this->emailRepo->getStaleCount(60);
            if ($staleEmails > 0) {
                $items[] = new NotificationItem(
                    label: $staleEmails . ' Stale Email' . ($staleEmails > 1 ? 's' : ''),
                    icon: 'fa-envelope',
                );
            }

            $pendingApproval = $this->userRepo->getUnverifiedCount();
            if ($pendingApproval > 0) {
                $items[] = new NotificationItem(
                    label: $pendingApproval . ' Pending Approval',
                    icon: 'fa-user-clock',
                    route: 'app_admin_member',
                );
            }
        }

        return $items;
    }
}
