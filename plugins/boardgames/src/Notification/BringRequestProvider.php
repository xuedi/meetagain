<?php declare(strict_types=1);

namespace Plugin\Boardgames\Notification;

use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use Override;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Service\BringRequestService;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BringRequestProvider implements NotificationProviderInterface
{
    public function __construct(
        private BringRequestService $requests,
        private TranslatorInterface $translator,
    ) {}

    #[Override]
    public function getNotifications(User $user): array
    {
        $items = [];
        foreach ($this->requests->getOpenForOwner($user) as $request) {
            $eventId = $request->getEvent()?->getId();
            if ($eventId === null) {
                continue;
            }

            $items[] = new NotificationItem(
                label: $this->translator->trans('boardgames_notifications.bring_request', [
                    '%requester%' => $request->getRequestedBy()?->getName() ?? '',
                    '%game%' => $this->gameName($request),
                ]),
                icon: 'fa-dice',
                route: 'app_event_details',
                routeParams: ['id' => $eventId],
            );
        }

        return $items;
    }

    private function gameName(BringRequest $request): string
    {
        return $request->getGame()?->getName() ?? '';
    }
}
