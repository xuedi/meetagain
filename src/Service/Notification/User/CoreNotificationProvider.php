<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Repository\EmailQueueRepository;
use App\Repository\SupportRequestRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class CoreNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private EmailQueueRepository $emailRepo,
        private SupportRequestRepository $supportRequestRepo,
        private Security $security,
    ) {}


    public function getNotifications(User $user): array
    {
        $items = [];
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return $items; // only Admin from here on
        }

        $staleEmails = $this->emailRepo->getStaleCount(60);
        if ($staleEmails > 0) {
            $items[] = new NotificationItem(
                label: $staleEmails . ' Stale Email' . ($staleEmails > 1 ? 's' : ''),
                icon: 'fa-envelope',
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
