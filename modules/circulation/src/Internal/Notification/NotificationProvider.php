<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Notification;

use App\Comment\CommentService;
use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use Module\Circulation\Internal\Comment\HandoverTargetProvider;
use Module\Circulation\Internal\Entity\Handover;
use Module\Circulation\Internal\Repository\CopyRepository;
use Module\Circulation\Internal\Repository\HandoverRepository;
use Module\Circulation\Internal\Repository\RequestRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class NotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private HandoverRepository $handovers,
        private CopyRepository $copies,
        private RequestRepository $requests,
        private CommentService $comments,
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
                key: 'circulation_queue_waiting',
            );
        }

        return $items;
    }

    /**
     * @return list<NotificationItem>
     */
    private function handoverItems(Handover $handover, User $user): array
    {
        $items = [];
        $isReceiver = $handover->getToUser()->getId() === $user->getId();
        $routeParams = ['id' => $handover->getId()];

        if (!$handover->hasConfirmed($user)) {
            $items[] = new NotificationItem(
                label: $this->translator->trans(
                    $isReceiver ? 'chrome.notification_circulation_ready_for_you' : 'chrome.notification_circulation_confirm_handover',
                ),
                icon: 'fa-handshake',
                route: 'app_circulation_handover',
                routeParams: $routeParams,
                key: $isReceiver ? 'circulation_ready_for_you' : 'circulation_confirm_handover',
            );
        }

        if ($this->comments->isUnreadBy(HandoverTargetProvider::TYPE, (int) $handover->getId(), (int) $user->getId())) {
            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_circulation_new_message'),
                icon: 'fa-comment',
                route: 'app_circulation_handover',
                routeParams: $routeParams,
                key: 'circulation_new_message',
            );
        }

        return $items;
    }
}
