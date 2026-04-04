<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Notification;

use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use Plugin\Dinnerclub\Repository\DishRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class DinnerclubSuggestionNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private DishRepository $dishRepository,
        private Security $security,
    ) {}

    public function getPriority(): int
    {
        return 20;
    }

    public function getNotifications(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        $count = $this->dishRepository->countWithSuggestions();
        if ($count === 0) {
            return [];
        }

        return [
            new NotificationItem(
                label: $count . ' Dish Suggestion' . ($count > 1 ? 's' : '') . ' Pending Review',
                icon: 'fa-utensils',
                route: 'plugin_dinnerclub_suggestions_list',
            ),
        ];
    }
}
