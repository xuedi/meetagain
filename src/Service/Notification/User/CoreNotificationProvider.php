<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Service\Support\VisibilityResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CoreNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private VisibilityResolver $visibilityResolver,
        private Security $security,
        private TranslatorInterface $translator,
    ) {}

    public function getNotifications(User $user): array
    {
        $items = [];
        if (!$this->security->isGranted('ROLE_STEWARD')) {
            return $items;
        }

        $newSupportRequests = $this->visibilityResolver->countNew();
        if ($newSupportRequests > 0) {
            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_new_support_requests', [
                    '%count%' => $newSupportRequests,
                ]),
                icon: 'fa-life-ring',
                route: 'app_admin_support_list',
            );
        }

        return $items;
    }
}
