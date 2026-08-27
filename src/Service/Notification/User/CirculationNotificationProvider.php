<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Circulation\Comment\HandoverTargetProvider;
use App\Entity\CirculationHandover;
use App\Entity\User;
use App\Repository\CirculationCopyRepository;
use App\Repository\CirculationHandoverRepository;
use App\Repository\CirculationRequestRepository;
use App\Repository\CommentRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class CirculationNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private CirculationHandoverRepository $handovers,
        private CirculationCopyRepository $copies,
        private CirculationRequestRepository $requests,
        private CommentRepository $comments,
        private TranslatorInterface $translator,
    ) {}

    public function getNotifications(User $user): array
    {
        $items = [];
        foreach ($this->handovers->findOpenForUser($user) as $handover) {
            $items = array_merge($items, $this->handoverItems($handover, $user));
        }

        foreach ($this->copies->findHeldBy((int) $user->getId()) as $copy) {
            $queue = $this->requests->findQueue($copy->getContext(), $copy->getItemType(), $copy->getItemId());
            if ($queue === []) {
                continue;
            }

            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_circulation_queue_waiting', ['%count%' => count($queue)]),
                icon: 'fa-people-arrows',
                route: 'app_circulation_dashboard',
                routeParams: ['itemType' => $copy->getItemType()],
            );
        }

        return $items;
    }

    /**
     * @return list<NotificationItem>
     */
    private function handoverItems(CirculationHandover $handover, User $user): array
    {
        $items = [];
        $isReceiver = $handover->getToUser()->getId() === $user->getId();
        $routeParams = ['id' => $handover->getId()];

        if (!$handover->hasConfirmed($user)) {
            $items[] = new NotificationItem(
                label: $this->translator->trans($isReceiver
                    ? 'chrome.notification_circulation_ready_for_you'
                    : 'chrome.notification_circulation_confirm_handover'),
                icon: 'fa-handshake',
                route: 'app_circulation_handover',
                routeParams: $routeParams,
            );
        }

        if ($this->hasUnreadChat($handover, $user)) {
            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_circulation_new_message'),
                icon: 'fa-comment',
                route: 'app_circulation_handover',
                routeParams: $routeParams,
            );
        }

        return $items;
    }

    private function hasUnreadChat(CirculationHandover $handover, User $user): bool
    {
        $thread = $this->comments->findForTarget(HandoverTargetProvider::TYPE, (int) $handover->getId());
        if ($thread === []) {
            return false;
        }

        $latest = $thread[0];
        if ($latest->getUser()?->getId() === $user->getId()) {
            return false;
        }

        foreach ($thread as $comment) {
            if ($comment->getUser()?->getId() === $user->getId()) {
                return $comment->getCreatedAt() < $latest->getCreatedAt();
            }
        }

        return true;
    }
}
