<?php

declare(strict_types=1);

namespace Plugin\Filmclub\Notification;

use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use Plugin\Filmclub\Service\PollService;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class FilmclubNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private PollService $pollService,
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
}
