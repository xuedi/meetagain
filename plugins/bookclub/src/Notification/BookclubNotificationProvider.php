<?php declare(strict_types=1);

namespace Plugin\Bookclub\Notification;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use Plugin\Bookclub\Activity\Messages\BookApproved;
use Plugin\Bookclub\Activity\Messages\BookRejected;
use Plugin\Bookclub\Repository\BookRepository;
use Plugin\Bookclub\Service\BookService;
use Plugin\Bookclub\Service\PollService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class BookclubNotificationProvider implements NotificationProviderInterface, ReviewNotificationProviderInterface
{
    public function __construct(
        private BookRepository $bookRepository,
        private BookService $bookService,
        private UserRepository $userRepository,
        private PollService $pollService,
        private ActivityService $activityService,
        private Security $security,
    ) {}


    public function getIdentifier(): string
    {
        return 'bookclub.book_approval';
    }

    public function getNotifications(User $user): array
    {
        $notifications = [];

        $activePoll = $this->pollService->getActivePoll();
        if ($activePoll !== null) {
            $vote = $this->pollService->getUserVote($activePoll->getId(), $user->getId());
            if ($vote === null) {
                $notifications[] = new NotificationItem(
                    label: 'Open book poll - cast your vote!',
                    icon: 'fa-vote-yea',
                    route: 'app_plugin_bookclub_poll',
                );
            }
        }

        return $notifications;
    }

    public function getReviewItems(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        $pendingBooks = $this->bookRepository->findBy(['approved' => false]);
        $items = [];

        foreach ($pendingBooks as $book) {
            $creatorName = 'unknown';
            if ($book->getCreatedBy() !== null) {
                $creator = $this->userRepository->find($book->getCreatedBy());
                if ($creator !== null) {
                    $creatorName = $creator->getName();
                }
            }

            $items[] = new ReviewNotificationItem(
                id: (string) $book->getId(),
                description: sprintf("Book '%s' suggested by %s is pending approval", $book->getTitle() ?? '', $creatorName),
                canDeny: true,
                icon: 'book',
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organizers can approve books.');
        }

        $book = $this->bookService->get((int) $itemId);
        $this->bookService->approve((int) $itemId);

        if ($book !== null) {
            $this->activityService->log(BookApproved::TYPE, $user, [
                'book_id' => $book->getId(),
                'book_title' => $book->getTitle() ?? '',
            ]);
        }
    }

    public function denyItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organizers can reject books.');
        }

        $book = $this->bookService->get((int) $itemId);
        $this->bookService->reject((int) $itemId);

        if ($book !== null) {
            $this->activityService->log(BookRejected::TYPE, $user, [
                'book_id' => $book->getId(),
                'book_title' => $book->getTitle() ?? '',
            ]);
        }
    }
}
