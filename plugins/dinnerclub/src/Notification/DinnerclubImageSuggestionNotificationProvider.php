<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Notification;

use App\Entity\User;
use App\Service\Notification\User\NotificationItem;
use App\Service\Notification\User\NotificationProviderInterface;
use Plugin\Dinnerclub\Repository\DishImageSuggestionRepository;
use Symfony\Bundle\SecurityBundle\Security;

readonly class DinnerclubImageSuggestionNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private DishImageSuggestionRepository $repository,
        private Security $security,
    ) {}

    public function getPriority(): int
    {
        return 21;
    }

    public function getNotifications(User $user): array
    {
        if (!$this->security->isGranted('ROLE_ORGANIZER')) {
            return [];
        }

        $count = $this->repository->count([]);
        if ($count === 0) {
            return [];
        }

        return [
            new NotificationItem(
                label: $count . ' Dish Image Suggestion' . ($count > 1 ? 's' : '') . ' Pending Review',
                icon: 'fa-image',
                route: 'plugin_dinnerclub_image_suggestions_list',
            ),
        ];
    }
}
