<?php declare(strict_types=1);

namespace Module\Email\Internal\Notification;

use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use Module\Email\Internal\Repository\EmailQueueRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class StaleQueueNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private EmailQueueRepository $repo,
        private Security $security,
        private TranslatorInterface $translator,
    ) {}

    public function getNotifications(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return [];
        }

        $stale = $this->repo->getStaleCount(60);
        if ($stale === 0) {
            return [];
        }

        return [
            new NotificationItem(
                label: $this->translator->trans('chrome.notification_stale_emails', ['%count%' => $stale]),
                icon: 'fa-envelope',
                route: 'app_admin_email_sendlog',
            ),
        ];
    }
}
