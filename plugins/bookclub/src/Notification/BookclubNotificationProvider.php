<?php declare(strict_types=1);

namespace Plugin\Bookclub\Notification;

use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use Plugin\Bookclub\Repository\BookRepository;
use Plugin\Bookclub\Service\PollService;
use Symfony\Bundle\SecurityBundle\Security;

readonly class BookclubNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private BookRepository $bookRepository,
        private PollService $pollService,
        private Security $security,
    ) {}

    public function getPriority(): int
    {
        return 10;
    }

    public function getNotifications(User $user): array
    {
        $notifications = [];

        if ($this->security->isGranted('ROLE_ORGANIZER')) {
            $pendingCount = count($this->bookRepository->findBy(['approved' => false]));
            if ($pendingCount > 0) {
                $notifications[] = new NotificationItem(
                    label: $pendingCount . ' Book' . ($pendingCount > 1 ? 's' : '') . ' Pending Approval',
                    icon: 'fa-book',
                    route: 'app_plugin_bookclub_pending',
                );
            }
        }

        $activePoll = $this->pollService->getActivePoll();
        if ($activePoll !== null) {
            $vote = $this->pollService->getUserVote($activePoll->getId(), $user->getId());
            if ($vote === null) {
                $notifications[] = new NotificationItem(
                    label: 'Open book poll — cast your vote!',
                    icon: 'fa-vote-yea',
                    route: 'app_plugin_bookclub_poll',
                );
            }
        }

        return $notifications;
    }
}
