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

        $waiting = $this->visibilityResolver->countWaiting($user);
        if ($waiting['involved'] > 0) {
            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_new_support_requests', [
                    '%count%' => $waiting['involved'],
                ]),
                icon: 'fa-life-ring',
                route: 'app_admin_support_list',
            );
        }

        if ($waiting['elsewhere'] > 0) {
            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_support_requests_elsewhere', [
                    '%count%' => $waiting['elsewhere'],
                ]),
                icon: 'fa-life-ring',
                route: 'app_admin_support_list',
                key: 'support_requests_elsewhere',
                level: Level::Info,
            );
        }

        return $items;
    }
}
