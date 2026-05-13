<?php

declare(strict_types=1);

namespace Plugin\Filmclub\Notification;

use App\Activity\ActivityService;
use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use App\Service\Notification\User\ReviewNotificationItem;
use App\Service\Notification\User\ReviewNotificationProviderInterface;
use Plugin\Filmclub\Activity\Messages\FilmApproved;
use Plugin\Filmclub\Activity\Messages\FilmRejected;
use Plugin\Filmclub\Service\FilmService;
use Plugin\Filmclub\Service\PollService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class FilmclubNotificationProvider implements
    NotificationProviderInterface,
    ReviewNotificationProviderInterface
{
    public function __construct(
        private PollService $pollService,
        private FilmService $filmService,
        private ActivityService $activityService,
        private Security $security,
        private TranslatorInterface $translator,
    ) {}

    public function getIdentifier(): string
    {
        return 'filmclub.film_approval';
    }

    public function getNotifications(User $user): array
    {
        $notifications = [];

        foreach ($this->pollService->getActivePolls() as $poll) {
            if ($this->pollService->hasUserVoted($poll, $user->getId())) {
                continue;
            }

            $notifications[] = new NotificationItem(
                label: $this->translator->trans('filmclub_notifications.open_poll_vote'),
                icon: 'fa-film',
                route: 'app_plugin_filmclub_poll_vote',
                routeParams: ['id' => $poll->getId()],
            );
        }

        return $notifications;
    }

    public function getReviewItems(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        $items = [];

        foreach ($this->filmService->getPendingList() as $film) {
            $items[] = new ReviewNotificationItem(
                id: (string) $film->getId(),
                description: $this->translator->trans('filmclub_notifications.review_pending_film', [
                    '%title%' => $film->getTitle() ?? '',
                ]),
                canDeny: true,
                icon: 'film',
            );
        }

        return $items;
    }

    public function approveItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organisers can approve films.');
        }

        $film = $this->filmService->get((int) $itemId);
        $this->filmService->approve((int) $itemId);

        if ($film !== null) {
            $this->activityService->log(FilmApproved::TYPE, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle() ?? '',
            ]);
        }
    }

    public function denyItem(User $user, string $itemId): void
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            throw new AccessDeniedException('Only organisers can reject films.');
        }

        $film = $this->filmService->get((int) $itemId);
        $this->filmService->reject((int) $itemId);

        if ($film !== null) {
            $this->activityService->log(FilmRejected::TYPE, $user, [
                'film_id' => $film->getId(),
                'film_title' => $film->getTitle() ?? '',
            ]);
        }
    }
}
