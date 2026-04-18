<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ReviewNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private ReviewNotificationService $service,
        private TranslatorInterface $translator,
    ) {}


    public function getNotifications(User $user): array
    {
        $count = $this->service->countForUser($user);
        if ($count === 0) {
            return [];
        }

        $label = $this->translator->trans('notification.review.pending_count', ['%count%' => $count]);

        return [
            new NotificationItem(
                label: $label,
                icon: 'fa-check-double',
                route: 'app_profile_review',
            ),
        ];
    }
}
