<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Repository\ImageRepository;
use App\Repository\SupportRequestRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class CoreNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private ImageRepository $imageRepo,
        private EmailQueueRepository $emailRepo,
        private UserRepository $userRepo,
        private SupportRequestRepository $supportRequestRepo,
        private Security $security,
    ) {
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getNotifications(User $user): array
    {
        $items = [];
        if (!$this->security->isGranted('ROLE_FOUNDER')) {
            return $items; // only FOUNDER from here on
        }

        $reportedCount = $this->imageRepo->getReportedCount();
        if ($reportedCount > 0) {
            $items[] = new NotificationItem(
                label: $reportedCount . ' Reported Image' . ($reportedCount > 1 ? 's' : ''),
                icon: 'fa-flag',
            );
        }

        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return $items; // only ADMIN from here on
        }

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

        $newSupportRequests = $this->supportRequestRepo->getNewCount();
        if ($newSupportRequests > 0) {
            $items[] = new NotificationItem(
                label: $newSupportRequests . ' New Support Request' . ($newSupportRequests > 1 ? 's' : ''),
                icon: 'fa-life-ring',
                route: 'app_admin_support_list',
            );
        }


        return $items;
    }
}
