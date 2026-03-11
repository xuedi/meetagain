<?php declare(strict_types=1);

namespace Plugin\Bookclub\Notification;

use App\Entity\User;
use App\Notification\NotificationItem;
use App\Notification\NotificationProviderInterface;
use Plugin\Bookclub\Repository\BookRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class BookclubNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private BookRepository $bookRepository,
        private Security $security,
    ) {}

    public function getPriority(): int
    {
        return 10;
    }

    public function getNotifications(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        $pendingCount = count($this->bookRepository->findBy(['approved' => false]));
        if ($pendingCount === 0) {
            return [];
        }

        return [
            new NotificationItem(
                label: $pendingCount . ' Book' . ($pendingCount > 1 ? 's' : '') . ' Pending Approval',
                icon: 'fa-book',
                route: 'app_plugin_bookclub_pending',
            ),
        ];
    }
}
